<?php

namespace Drupal\Console\KumquatScaffolder\Command;

use Drupal\Console\Command\Shared\ConfirmationTrait;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\KumquatScaffolder\Generator\ProjectGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drupal console command to generate project parts.
 */
class GenerateProjectCommand extends Command {

  use ConfirmationTrait;

  const REGEX_MACHINE_NAME = '/^[a-z0-9_]+$/';

  /**
   * The generator.
   *
   * @var \Drupal\Console\KumquatScaffolder\Generator\ProjectGenerator
   */
  protected $generator;

  /**
   * The string converter service.
   *
   * @var \Drupal\Console\Core\Utils\StringConverter
   */
  protected $stringConverter;

  /**
   * The document root absolute path.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Class constructor.
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
  protected function configure() {
    $this
      ->setName('kumquat:generate-project')
      ->setAliases(['kgp'])
      ->setDescription('Generate an install profile, a core module, a default theme and/or an admin theme.')
      ->addOption(
        'name',
        NULL,
        InputOption::VALUE_REQUIRED,
        'The project readable name (ex: Happyculture).'
      )
      ->addOption(
        'machine-name',
        NULL,
        InputOption::VALUE_REQUIRED,
        'The project (short) machine name (ex: hc).'
      )
      ->addOption(
        'config-folder',
        NULL,
        InputOption::VALUE_REQUIRED,
        'The configuration storage folder, relative to the document root.'
      )
      ->addOption(
        'generate-all',
        NULL,
        InputOption::VALUE_NONE,
        'Generate a new install profile, a core module, an admin theme, a front theme and the associated configuration.'
      )
      ->addOption(
        'generate-profile',
        NULL,
        InputOption::VALUE_NONE,
        'Generate a new install profile.'
      )
      ->addOption(
        'generate-core-module',
        NULL,
        InputOption::VALUE_NONE,
        'Generate a new core module.'
      )
      ->addOption(
        'generate-theme',
        NULL,
        InputOption::VALUE_NONE,
        'Generate a front theme.'
      )
      ->addOption(
        'generate-admin-theme',
        NULL,
        InputOption::VALUE_NONE,
        'Generate an administration theme.'
      )
      ->addOption(
        'generate-config',
        NULL,
        InputOption::VALUE_NONE,
        'Change the config to use the new profile and themes by default.'
      )
      ->addOption(
        'base-admin-theme',
        NULL,
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
    $generate_all = (bool) $input->getOption('generate-all');
    $generate_profile = (bool) $input->getOption('generate-profile');
    $generate_core_module = (bool) $input->getOption('generate-core-module');
    $generate_theme = (bool) $input->getOption('generate-theme');
    $generate_admin_theme = (bool) $input->getOption('generate-admin-theme');
    $generate_config = (bool) $input->getOption('generate-config');
    $base_admin_theme = $this->validateMachineName($input->getOption('base-admin-theme'));
    $theme_folder = 'themes/custom';
    $module_folder = 'modules/custom';
    $profiles_folder = 'profiles';

    // Improve attributes readibility.
    $recap_gen_profile = $generate_profile || $generate_all ? 'Yes' : 'No';
    $recap_gen_core_module = $generate_core_module || $generate_all ? 'Yes' : 'No';
    $recap_gen_theme = $generate_theme || $generate_all ? 'Yes' : 'No';
    $recap_gen_admin_theme = $generate_admin_theme || $generate_all ? 'Yes' : 'No';
    $recap_gen_config = $generate_config || $generate_all ? 'Yes' : 'No';

    $recap_params = [
      ['Name', $name],
      ['Machine name', $machine_name],
    ];
    $recap_params[] = ['Generate profile', $recap_gen_profile];
    if ($generate_profile || $generate_all) {
      $recap_params[] = ['Profiles folder', $profiles_folder];
    }
    $recap_params[] = ['Generate core module', $recap_gen_core_module];
    if ($generate_core_module || $generate_all) {
      $recap_params[] = ['Modules folder', $module_folder];
    }
    $recap_params[] = ['Generate front theme', $recap_gen_theme];
    $recap_params[] = ['Generate admin theme', $recap_gen_admin_theme];
    if ($generate_admin_theme || $generate_all) {
      $recap_params[] = ['Base admin theme', $base_admin_theme];
    }
    if ($generate_admin_theme || $generate_theme || $generate_all) {
      $recap_params[] = ['Themes folder', $theme_folder];
    }
    $recap_params[] = ['Generate config', $recap_gen_config];
    if ($generate_config || $generate_all) {
      $recap_params[] = ['Config folder', $config_folder];
    }

    $this->getIo()->newLine(1);
    $this->getIo()->commentBlock('Settings recap');
    $this->getIo()->table(['Parameter', 'Value'], $recap_params);

    // @see use Drupal\Console\Command\Shared\ConfirmationTrait::confirmOperation
    if (!$this->confirmOperation()) {
      return 1;
    }

    if ($generate_profile || $generate_all) {
      $this->generator->generateProfile([
        'name' => $name,
        'machine_name' => $machine_name,
        'profiles_dir' => $profiles_folder,
      ]);
    }
    if ($generate_core_module || $generate_all) {
      $this->generator->generateCoreModule([
        'name' => $name,
        'machine_name' => $machine_name,
        'modules_dir' => $module_folder,
      ]);
    }
    if ($generate_theme || $generate_all) {
      $this->generator->generateDefaultTheme([
        'name' => $name,
        'machine_name' => $machine_name,
        'themes_dir' => $theme_folder,
      ]);
    }
    if ($generate_admin_theme || $generate_all) {
      $this->generator->generateAdminTheme([
        'name' => $name,
        'machine_name' => $machine_name,
        'themes_dir' => $theme_folder,
        'base_admin_theme' => $base_admin_theme,
      ]);
    }
    if ($generate_config || $generate_all) {
      $this->generator->generateConfig([
        'name' => $name,
        'machine_name' => $machine_name,
        'config_folder' => $config_folder,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    try {
      $generator_parts = [
        'all',
        'profile',
        'core-module',
        'theme',
        'admin-theme',
        'config',
      ];

      $enabled_parts = [];
      foreach ($generator_parts as $part) {
        $enabled_parts[$part] = !empty($input->getOption('generate-' . $part));
      }
      $enabled_parts = array_filter($enabled_parts);

      if (empty($enabled_parts)) {
        /** @var array $enabled_parts */
        $enabled_parts = $this->getIo()->choice(
          'What do you want to generate? Use comma separated values for multiple selection.',
          $generator_parts,
          implode(',', array_keys($enabled_parts)),
          TRUE
        );

        if (empty($enabled_parts)) {
          throw new \Exception('You must at least choose one thing to generate.');
        }

        foreach ($generator_parts as $part) {
          $input->setOption('generate-' . $part, in_array($part, $enabled_parts));
        }
      }
    }
    catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    try {
      // A profile is technically also a module, so we can use the same
      // validator to check the name.
      $name = $input->getOption('name') ? $this->validateName($input->getOption('name')) : NULL;

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
    }
    catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    try {
      $machine_name = $input->getOption('machine-name') ? $this->validateMachineName($input->getOption('machine-name')) : NULL;
      if (!$machine_name) {
        $machine_name = $this->getIo()->ask(
          'What is the machine name of the project?',
          $this->stringConverter->createMachineName($name),
          function ($machine_name) {
            return empty($machine_name) ? '' : $this->validateMachineName($machine_name);
          }
        );
        $input->setOption('machine-name', $machine_name);
      }
    }
    catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    if (in_array('config', $enabled_parts) || in_array('all', $enabled_parts)) {
      try {
        $config_folder = $input->getOption('config-folder') ? $this->validatePath($input->getOption('config-folder')) : NULL;
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
      }
      catch (\Exception $error) {
        $this->getIo()->error($error->getMessage());
        return 1;
      }
    }

    if (in_array('admin-theme', $enabled_parts) || in_array('all', $enabled_parts)) {
      try {
        $base_admin_theme = $input->getOption('base-admin-theme') ? $input->getOption('base-admin-theme') : NULL;
        if (empty($base_admin_theme)) {
          $base_admin_theme = $this->getIo()->choice(
            'Which theme you want your administration theme based on? (if you want another one, use the --base-admin-theme option.',
            ['adminimal_theme', 'gin'],
            'gin'
          );
          $input->setOption('base-admin-theme', $base_admin_theme);
        }
      }
      catch (\Exception $error) {
        $this->getIo()->error($error->getMessage());
        return 1;
      }
    }

  }

  /**
   * Validates a module name.
   *
   * @param string $module
   *   The module name.
   *
   * @return string
   *   The module name.
   *
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
   *
   * @return string
   *   The machine name.
   *
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
   *
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

}
