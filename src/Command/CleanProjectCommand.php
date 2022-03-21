<?php

namespace Drupal\Console\KumquatScaffolder\Command;

use Drupal\Console\Command\Shared\ConfirmationTrait;
use Drupal\Console\Core\Command\Command;
use Drupal\Console\Core\Utils\StringConverter;
use Drupal\Console\KumquatScaffolder\ConfigManipulationTrait;
use Drupal\Console\KumquatScaffolder\FileManipulationTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drupal console command to remove project parts.
 */
class CleanProjectCommand extends Command {

  use ConfigManipulationTrait;
  use ConfirmationTrait;
  use FileManipulationTrait;

  const REGEX_MACHINE_NAME = '/^[a-z0-9_]+$/';

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
    StringConverter $stringConverter,
    $app_root
  ) {
    $this->stringConverter = $stringConverter;
    $this->appRoot = $app_root;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('kumquat:clean-project')
      ->setAliases(['kcp'])
      ->setDescription('Remove an install profile, a core module, a default theme and/or an admin theme.')
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
        'clean-all',
        NULL,
        InputOption::VALUE_NONE,
        'Clean the install profile, the core module, the admin theme, the front theme and the associated configuration.'
      )
      ->addOption(
        'clean-profile',
        NULL,
        InputOption::VALUE_NONE,
        'Clean the install profile.'
      )
      ->addOption(
        'clean-core-module',
        NULL,
        InputOption::VALUE_NONE,
        'Clean the core module.'
      )
      ->addOption(
        'clean-theme',
        NULL,
        InputOption::VALUE_NONE,
        'Clean the front theme.'
      )
      ->addOption(
        'clean-admin-theme',
        NULL,
        InputOption::VALUE_NONE,
        'Clean the administration theme.'
      )
      ->addOption(
        'clean-config',
        NULL,
        InputOption::VALUE_NONE,
        'Change the config to use the default profile and themes by default.'
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $machine_name = $this->validateMachineName($input->getOption('machine-name'));
    $config_folder = $this->validatePath($input->getOption('config-folder'));
    $clean_all = (bool) $input->getOption('clean-all');
    $clean_profile = (bool) $input->getOption('clean-profile');
    $clean_core_module = (bool) $input->getOption('clean-core-module');
    $clean_theme = (bool) $input->getOption('clean-theme');
    $clean_admin_theme = (bool) $input->getOption('clean-admin-theme');
    $clean_config = (bool) $input->getOption('clean-config');
    $theme_folder = 'themes/custom';
    $module_folder = 'modules/custom';
    $profiles_folder = 'profiles';

    // Improve attributes readibility.
    $recap_gen_profile = $clean_profile || $clean_all ? 'Yes' : 'No';
    $recap_gen_core_module = $clean_core_module || $clean_all ? 'Yes' : 'No';
    $recap_gen_theme = $clean_theme || $clean_all ? 'Yes' : 'No';
    $recap_gen_admin_theme = $clean_admin_theme || $clean_all ? 'Yes' : 'No';
    $recap_gen_config = $clean_config || $clean_all ? 'Yes' : 'No';

    $recap_params = [
      ['Machine name', $machine_name],
    ];
    $recap_params[] = ['Clean profile', $recap_gen_profile];
    $recap_params[] = ['Clean core module', $recap_gen_core_module];
    $recap_params[] = ['Clean front theme', $recap_gen_theme];
    $recap_params[] = ['Clean admin theme', $recap_gen_admin_theme];
    $recap_params[] = ['Clean config', $recap_gen_config];
    if ($clean_config || $clean_all) {
      $recap_params[] = ['Config folder', $config_folder];
    }

    $this->getIo()->newLine(1);
    $this->getIo()->commentBlock('Settings recap');
    $this->getIo()->table(['Parameter', 'Value'], $recap_params);

    // @see use Drupal\Console\Command\Shared\ConfirmationTrait::confirmOperation
    if (!$this->confirmOperation()) {
      return 1;
    }

    if ($clean_profile || $clean_all) {
      $this->cleanProfile($profiles_folder, $machine_name);
    }

    if ($clean_core_module || $clean_all) {
      $this->cleanCoreModule($module_folder, $machine_name);
    }

    if ($clean_theme || $clean_all) {
      $this->cleanTheme($theme_folder, $config_folder, $machine_name);
    }

    if ($clean_admin_theme || $clean_all) {
      $this->cleanAdminTheme($theme_folder, $config_folder, $machine_name);
    }

    if ($clean_config || $clean_all) {
      $this->cleanConfig($config_folder, $machine_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    try {
      $cleaner_parts = [
        'all',
        'profile',
        'core-module',
        'theme',
        'admin-theme',
        'config',
      ];

      $enabled_parts = [];
      foreach ($cleaner_parts as $part) {
        $enabled_parts[$part] = !empty($input->getOption('clean-' . $part));
      }
      $enabled_parts = array_filter($enabled_parts);

      if (empty($enabled_parts)) {
        /** @var array $enabled_parts */
        $enabled_parts = $this->getIo()->choice(
          'What do you want to remove? Use comma separated values for multiple selection.',
          $cleaner_parts,
          implode(',', array_keys($enabled_parts)),
          TRUE
        );

        if (empty($enabled_parts)) {
          throw new \Exception('You must at least choose one thing to remove.');
        }

        foreach ($cleaner_parts as $part) {
          $input->setOption('clean-' . $part, in_array($part, $enabled_parts));
        }
        $enabled_parts = array_fill_keys(array_values($enabled_parts), TRUE);
      }
    }
    catch (\Exception $error) {
      $this->getIo()->error($error->getMessage());
      return 1;
    }

    try {
      $machine_name = $input->getOption('machine-name') ? $this->validateMachineName($input->getOption('machine-name')) : NULL;
      if (!$machine_name) {
        $composer_data = json_decode(file_get_contents($this->drupalFinder->getComposerRoot() . '/composer.json'))->name;
        [, $default_name] = explode('/', $composer_data);
        $machine_name = $this->getIo()->ask(
          'What is the machine name of the project?',
          $default_name,
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

    if ($enabled_parts['config'] || $enabled_parts['admin-theme'] || $enabled_parts['theme'] || $enabled_parts['all']) {
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

  /**
   * Clean install profile.
   *
   * @param string $profiles_folder
   *   The profiles storage folder.
   * @param string $machine_name
   *   The project machine name.
   */
  protected function cleanProfile(string $profiles_folder, string $machine_name): void {
    $dir = $profiles_folder . '/' . $machine_name;
    if ($this->getFs()->exists($dir)) {
      $this->getFs()->remove($dir);
      $this->getIo()->success(sprintf('%s profile successfully cleaned.', $machine_name));
    }
    else {
      $this->getIo()->info(sprintf('No %s profile to clean.', $machine_name));
    }
  }

  /**
   * Clean core module.
   *
   * @param string $module_folder
   *   The module storage folder.
   * @param string $machine_name
   *   The project machine name.
   */
  protected function cleanCoreModule(string $module_folder, string $machine_name): void {
    $dir = $module_folder . '/' . $machine_name . '_core';
    if ($this->getFs()->exists($dir)) {
      $this->getFs()->remove($dir);
      $this->getIo()->success(sprintf('%s core module successfully cleaned.', $machine_name . '_core'));
    }
    else {
      $this->getIo()->info(sprintf('No %s core module to clean.', $machine_name . '_core'));
    }
  }

  /**
   * Clean front theme.
   *
   * @param string $theme_folder
   *   The theme storage folder.
   * @param string $config_folder
   *   The configuration strorage folder.
   * @param string $machine_name
   *   The project machine name.
   */
  protected function cleanTheme(string $theme_folder, string $config_folder, string $machine_name): void {
    $dir = $theme_folder . '/' . $machine_name . '_theme';
    if ($this->getFs()->exists($dir)) {
      $this->getFs()->remove($dir);

      // Remove theme configuration.
      $this->getFs()->remove($config_folder . '/' . $machine_name . '_theme.settings.yml');

      // Remove block configuration in the config dir.
      $files = $this->getFinder()->in($config_folder)
        ->files()->name('block.block.' . $machine_name . '_theme_*.yml');
      $this->getFs()->remove($files);

      // Remove the theme path in the composer.json file.
      $prevDir = getcwd();
      chdir($this->drupalFinder->getComposerRoot());
      $enabledThemes = json_decode(exec('/usr/bin/env composer config extra.kumquat-themes'));

      $prefix = substr($this->drupalFinder->getDrupalRoot(), strlen($this->drupalFinder->getComposerRoot()));
      $prefix = trim($prefix, '/');
      $key = array_search($prefix . '/' . $dir, $enabledThemes);
      if ($key !== FALSE) {
        unset($enabledThemes[$key]);
      }

      exec('/usr/bin/env composer config extra.kumquat-themes --json \'' . json_encode(array_unique($enabledThemes)) . '\'');
      exec('/usr/bin/env composer update --lock');

      chdir($prevDir);

      $this->getIo()->success(sprintf('%s front theme successfully cleaned.', $machine_name . '_theme'));
    }
    else {
      $this->getIo()->info(sprintf('No %s front theme to clean.', $machine_name . '_theme'));
    }
  }

  /**
   * Clean administration theme.
   *
   * @param string $theme_folder
   *   The theme storage folder.
   * @param string $config_folder
   *   The configuration strorage folder.
   * @param string $machine_name
   *   The project machine name.
   */
  protected function cleanAdminTheme(string $theme_folder, string $config_folder, string $machine_name): void {
    $dir = $theme_folder . '/' . $machine_name . '_admin_theme';
    if ($this->getFs()->exists($dir)) {
      // Remove theme directory.
      $this->getFs()->remove($dir);

      // Remove theme configuration.
      $this->getFs()->remove($config_folder . '/' . $machine_name . '_admin_theme.settings.yml');

      $this->getIo()->success(sprintf('%s admin theme successfully cleaned.', $machine_name . '_admin_theme'));
    }
    else {
      $this->getIo()->info(sprintf('No %s admin theme to clean.', $machine_name . '_admin_theme'));
    }
  }

  /**
   * Clean configuration.
   *
   * @param string $config_folder
   *   The configuration strorage folder.
   * @param string $machine_name
   *   The project machine name.
   */
  protected function cleanConfig(string $config_folder, string $machine_name): void {
    // Set themes in the system.theme.yml file.
    $filename = $config_folder . '/system.theme.yml';
    $config = $this->readConfig($filename);

    if ($config['admin'] === $machine_name . '_admin_theme') {
      $config['admin'] = 'seven';
      $resetAdminTheme = TRUE;
    }
    if ($config['default'] === $machine_name . '_theme') {
      $config['default'] = 'bartik';
      $resetFrontTheme = TRUE;
    }

    $this->writeConfig($filename, $config);

    // Update profile and themes in the core.extension.yml file.
    $filename = $config_folder . '/core.extension.yml';
    $config = $this->readConfig($filename);
    $current_profile = $config['profile'];

    if ($current_profile === $machine_name) {
      $config['module']['minimal'] = 1000;
      $config['profile'] = 'minimal';
    }
    unset($config['module'][$machine_name]);
    unset($config['module'][$machine_name . '_core']);
    $config['module'] = module_config_sort($config['module']);

    if (isset($resetFrontTheme)) {
      $config['theme']['bartik'] = 0;
    }
    unset($config['theme'][$machine_name . '_theme']);
    if (isset($resetAdminTheme)) {
      $config['theme']['seven'] = 0;
    }
    unset($config['theme'][$machine_name . '_admin_theme']);

    $this->writeConfig($filename, $config);

    $this->getIo()->success('Configuration successfully cleaned.');
  }

}
