<?php

namespace Drupal\Console\KumquatScaffolder\Command;

use Drupal\Console\Command\Shared\ConfirmationTrait;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\KumquatScaffolder\Generator\ProjectGenerator;
use Drupal\Console\Extension\Manager;
use Drupal\Console\Utils\Validator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateProjectCommand extends Command {

  use ConfirmationTrait;

  const REGEX_MACHINE_NAME = '/^[a-z0-9_]+$/';

  /**
   * @var ProjectGenerator
   */
  protected $generator;

  /**
   * @var StringConverter
   */
  protected $stringConverter;

  /**
   * @var string The document root absolute path.
   */
  protected $appRoot;

  /**
   * ProfileCommand constructor.
   *
   * @param ProjectGenerator $generator
   * @param StringConverter  $stringConverter
   * @param string           $app_root
   */
  public function __construct(
    ProjectGenerator $generator,
    StringConverter $stringConverter,
    $app_root
  ) {
    $this->generator = $generator;
    $this->stringConverter = $stringConverter;
    $this->appRoot = $app_root;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('kumquat:generate-project')
      ->setAliases(['kgp'])
      ->setDescription('Generate an install profile, a default theme and an admin theme.')
      ->addOption(
        'core',
        null,
        InputOption::VALUE_REQUIRED,
        'Drupal core version built (7, 8, 9).'
      )->addOption(
        'name',
        null,
        InputOption::VALUE_REQUIRED,
        'The project readable name (ex: Happyculture).'
      )
      ->addOption(
        'machine-name',
        null,
        InputOption::VALUE_REQUIRED,
        'The project (short) machine name (ex: hc).'
      )
      ->addOption(
        'config-folder',
        null,
        InputOption::VALUE_REQUIRED,
        'The configuration storage folder, relative to the document root.'
      )
      ->addOption(
        'generate-config',
        null,
        InputOption::VALUE_NONE,
        'Change the config to use the new profile and themes by default.'
      )
      ->addOption(
        'base-admin-theme',
        null,
        InputOption::VALUE_REQUIRED,
        'The base theme of the administration theme.'
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $name = $this->validateName($input->getOption('name'));
    $machine_name = $this->validateMachineName($input->getOption('machine-name'));
    $config_folder = $this->validatePath($input->getOption('config-folder'));
    $generate_config = (bool) $input->getOption('generate-config');
    $core_version = $this->extractCoreVersion($input->getOption('core'));
    $base_admin_theme = $this->validateMachineName($input->getOption('base-admin-theme'));
    $theme_folder = 'themes/custom';
    $module_folder = 'modules/custom';
    $profiles_folder = 'profiles';

    // Improve attributes readibility.
    $recap_gen_config = $generate_config ? 'Yes' : 'No';

    $recap_params = [
      ['Core version', $core_version],
      ['Name', $name],
      ['Machine name', $machine_name],
      ['Base admin theme', $base_admin_theme],
      ['Generate config', $recap_gen_config],
      ['Config folder', $config_folder],
      ['Profiles folder', $profiles_folder],
      ['Modules folder', $module_folder],
      ['Themes folder', $theme_folder],
    ];

    $this->getIo()->newLine(1);
    $this->getIo()->commentBlock('Settings recap');
    $this->getIo()->table(['Parameter', 'Value'], $recap_params);

    // @see use Drupal\Console\Command\Shared\ConfirmationTrait::confirmOperation
    if (!$this->confirmOperation()) {
      return 1;
    }

    $this->generator->generate([
      'core' => $core_version,
      'name' => $name,
      'machine_name' => $machine_name,
      'base_admin_theme' => $base_admin_theme,
      'config_folder' => $config_folder,
      'generate_config' => $generate_config,
      'profiles_dir' => $profiles_folder,
      'modules_dir' => $module_folder,
      'themes_dir' => $theme_folder,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    // Identify the Drupal version built.
    try {
      $core_version = $input->getOption('core') ? $input->getOption('core') : null;
      if (empty($core_version)) {
        $core_version = $this->getIo()->choice(
          'With which version of Drupal will you run this project?',
          ['Drupal 7', 'Drupal 8', 'Drupal 9'],
          'Drupal 9'
        );
        $input->setOption('core', $core_version);
      }
      else if (!in_array($core_version, [7, 8, 9])) {
        throw new \InvalidArgumentException(sprintf('Invalid version "%s" specified (only 7 or 8 are supported at the moment).', $core_version));
      }
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    try {
      // A profile is technically also a module, so we can use the same
      // validator to check the name.
      $name = $input->getOption('name') ? $this->validateName($input->getOption('name')) : null;
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());

      return 1;
    }

    if (!$name) {
      $name = $this->getIo()->ask(
        'What is the human readable name of the project?',
        'Happy Rocket',
        function ($name) {
          return $this->validateName($name);
        }
      );
      $input->setOption('name', $name);
    }

    try {
      $machine_name = $input->getOption('machine-name') ? $this->validateMachineName($input->getOption('machine-name')) : null;
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());

      return 1;
    }

    if (!$machine_name) {
      $machine_name = $this->getIo()->ask(
        'What is the machine name of the project?',
        $this->stringConverter->createMachineName($name),
        function ($machine_name) {
          return $this->validateMachineName($machine_name);
        }
      );
      $input->setOption('machine-name', $machine_name);
    }

    try {
      $config_folder = $input->getOption('config-folder') ? $this->validatePath($input->getOption('config-folder')) : null;
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());

      return 1;
    }

    if (!$config_folder) {
      $config_folder = $this->getIo()->ask(
        'Where are the configuration files stored (relative to the document root)?',
        '../config/sync',
        function ($config_folder) {
          return $this->validatePath($config_folder);
        }
      );
      $input->setOption('config-folder', $config_folder);
    }

    try {
      $base_admin_theme = $input->getOption('base-admin-theme') ? $input->getOption('base-admin-theme') : null;
      if (empty($base_admin_theme)) {
        $base_admin_theme = $this->getIo()->choice(
          'Which theme you want your administration theme based on? (if you want another one, use the --base-admin-theme option.',
          ['adminimal_theme', 'gin'],
          'gin'
        );
        $input->setOption('base-admin-theme', $base_admin_theme);
      }
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    try {
      $generate_config = $input->getOption('generate-config') ? (bool) $input->getOption('generate-config') : null;
    } catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());

      return 1;
    }

    if (!$generate_config) {
      $generate_config = $this->getIo()->confirm(
        'Do you want the config to be changed so the new profile and themes are used by default?',
        TRUE
      );
      $input->setOption('generate-config', $generate_config);
    }
  }

  /**
   * Validates a module name.
   *
   * @param string $module
   *   The module name.
   * @return string
   *   The module name.
   * @throws \InvalidArgumentException
   */
  protected function validateName($module) {
    if (!empty($module)) {
      return $module;
    }
    else {
      throw new \InvalidArgumentException(sprintf('Name "%s" is invalid.', $module));
    }
  }

  /**
   * Validates a machine name.
   *
   * @param string $machine_name
   *   The machine name.
   * @return string
   *   The machine name.
   * @throws \InvalidArgumentException
   */
  protected function validateMachineName($machine_name) {
    if (preg_match(self::REGEX_MACHINE_NAME, $machine_name)) {
      return $machine_name;
    }
    else {
      throw new \InvalidArgumentException(
        sprintf(
          'Machine name "%s" is invalid, it must contain only lowercase letters, numbers and underscores.',
          $machine_name
        )
      );
    }
  }

  /**
   * Validates a path relative to the document root.
   *
   * @param string $path
   *   The path to validate.
   * @return string
   *   The path.
   */
  protected function validatePath($path) {
    $destination = $this->appRoot . '/' . $path;
    if (is_dir($destination)) {
      return $path;
    }
    else {
      throw new \InvalidArgumentException(
        sprintf(
          '"%s" is not an existing path.',
          $destination
        )
      );
    }
  }

  /**
   * @param $core_version
   */
  protected function extractCoreVersion($core_version) {
    $matches = [];
    if (preg_match('`^Drupal ([0-9]+)$`', $core_version, $matches)) {
      $core_version = $matches[1];
    }
    return $core_version;
  }
}
