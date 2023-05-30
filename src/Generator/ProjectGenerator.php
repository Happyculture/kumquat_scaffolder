<?php

namespace Drupal\Console\KumquatScaffolder\Generator;

use Drupal\Console\Core\Generator\Generator;
use Drupal\Console\Core\Utils\TwigRenderer;
use Drupal\Console\KumquatScaffolder\ConfigManipulationTrait;
use Drupal\Console\KumquatScaffolder\FileManipulationTrait;
use Drupal\Core\Serialization\Yaml;

/**
 * Generate project parts for some templates.
 */
class ProjectGenerator extends Generator {

  use ConfigManipulationTrait;
  use FileManipulationTrait;

  const TPL_DIR = __DIR__ . '/../../templates';

  /**
   * {@inheritdoc}
   */
  public function setRenderer(TwigRenderer $renderer) {
    $this->renderer = $renderer;
    $this->renderer->addSkeletonDir(self::TPL_DIR);
  }

  /**
   * Generates an installation profile.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateProfile(array $parameters) {
    $profiles_dir = $parameters['profiles_dir'];
    $machine_name = $parameters['machine_name'];

    $this->checkDir(($profiles_dir == '/' ? '' : $profiles_dir) . '/' . $machine_name, 'profile');

    $profilePath = ($profiles_dir == '/' ? '' : $profiles_dir) . '/' . $machine_name . '/' . $machine_name;
    $profileParameters = [
      'profile' => $parameters['name'],
      'machine_name' => $machine_name,
      'themes' => [$machine_name . '_theme', $machine_name . '_admin_theme'],
    ];

    $this->renderFile(
      'kumquat-profile/info.yml.twig',
      $profilePath . '.info.yml',
      $profileParameters
    );

    $this->renderFile(
      'kumquat-profile/profile.twig',
      $profilePath . '.profile',
      $profileParameters
    );

    $this->renderFile(
      'kumquat-profile/install.twig',
      $profilePath . '.install',
      $profileParameters
    );
  }

  /**
   * Generates the core module.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateCoreModule(array $parameters) {
    $modules = $parameters['modules_dir'];
    $machine_name = $parameters['machine_name'] . '_core';

    $this->checkDir(($modules == '/' ? '' : $modules) . '/' . $machine_name, 'core module');

    $modulePath = ($modules == '/' ? '' : $modules) . '/' . $machine_name . '/' . $machine_name;
    $profileParameters = [
      'profile' => $parameters['name'],
      'module' => $parameters['name'] . ' Core',
      'machine_name' => $machine_name,
    ];

    $this->renderFile(
      'kumquat-core-module/info.yml.twig',
      $modulePath . '.info.yml',
      $profileParameters
    );

    $this->renderFile(
      'kumquat-core-module/module.twig',
      $modulePath . '.module',
      $profileParameters
    );
  }

  /**
   * Generates content modules.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateContentModules(array $parameters) {
    $modules = $parameters['modules_dir'];
    foreach (['deploy', 'test'] as $mode) {
      $machine_name = $parameters['machine_name'] . '_content_' . $mode;

      $this->checkDir(($modules == '/' ? '' : $modules) . '/' . $machine_name, 'content modules');

      $modulePath = ($modules == '/' ? '' : $modules) . '/' . $machine_name . '/' . $machine_name;

      $moduleParameters = [
        'profile' => $parameters['name'],
        'module' => $parameters['name'] . ' Content ' . ucfirst($mode),
        'machine_name' => $machine_name,
        'mode' => $mode,
      ];

      $this->renderFile(
        'kumquat-content-modules/info.yml.twig',
        $modulePath . '.info.yml',
        $moduleParameters
      );

      $this->getFs()->mkdir(dirname($modulePath) . '/content', 0750);
      $this->getFs()->touch(dirname($modulePath) . '/content/.gitkeep');
      $this->trackGeneratedFile(dirname($modulePath) . '/content/.gitkeep');
    }
  }

  /**
   * Generates an administration theme based on Adminimal or Gin.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateAdminTheme(array $parameters) {
    $themes_dir = $parameters['themes_dir'];
    $machine_name = $parameters['machine_name'];
    $config_folder = $parameters['config_folder'];

    $this->checkDir(($themes_dir == '/' ? '' : $themes_dir) . '/' . $machine_name . '_admin_theme', 'admin theme');

    $adminThemePath = ($themes_dir == '/' ? '' : $themes_dir) . '/' . $machine_name . '_admin_theme' . '/' . $machine_name . '_admin_theme';
    $adminThemeParameters = [
      'profile' => $parameters['name'],
      'theme' => $parameters['name'] . ' Admin',
      'machine_name' => $machine_name . '_admin_theme',
      'base_admin_theme' => $parameters['base_admin_theme'],
    ];

    // Base files.
    $this->renderFile(
      'kumquat-admin-theme/info.yml.twig',
      $adminThemePath . '.info.yml',
      $adminThemeParameters
    );

    $this->renderFile(
      'kumquat-admin-theme/theme.twig',
      $adminThemePath . '.theme',
      $adminThemeParameters
    );

    $this->renderFile(
      'kumquat-admin-theme/libraries.yml.twig',
      $adminThemePath . '.libraries.yml',
      $adminThemeParameters
    );

    $this->renderFile(
      'kumquat-admin-theme/base.css.twig',
      dirname($adminThemePath) . '/css/' . $adminThemeParameters['machine_name'] . '.css',
      $adminThemeParameters
    );

    if (in_array($parameters['base_admin_theme'], ['gin', 'kumquat_gin'])) {
      $this->renderFile(
        'kumquat-admin-theme/admin_theme.settings.yml.twig',
        $config_folder . '/' . $machine_name . '_admin_theme.settings.yml',
        $adminThemeParameters
      );
    }

    // Blocks configuration.
    $dir = opendir(self::TPL_DIR . '/kumquat-admin-theme/config/blocks');
    while ($file = readdir($dir)) {
      if ($file[0] === '.') {
        continue;
      }

      $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
      $this->renderFile(
        'kumquat-admin-theme/config/blocks/' . $file,
        $config_folder . '/block.block.' . $machine_name . '_admin_theme_' . $block_id . '.yml',
        $adminThemeParameters
      );
    }

    $this->renderFile(
      'kumquat-admin-theme/config/settings.yml.twig',
      $config_folder . '/' . $adminThemeParameters['machine_name'] . '.settings.yml',
      $adminThemeParameters
    );
  }

  /**
   * Generates a theme based on Classy.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateDefaultTheme(array $parameters) {
    $themes_dir = $parameters['themes_dir'];
    $machine_name = $parameters['machine_name'];

    $this->checkDir(($themes_dir == '/' ? '' : $themes_dir) . '/' . $machine_name . '_theme', 'theme');

    $defaultThemePath = ($themes_dir == '/' ? '' : $themes_dir) . '/' . $machine_name . '_theme';
    $defaultThemeParameters = [
      'profile' => $parameters['name'],
      'theme' => $parameters['name'],
      'machine_name' => $machine_name . '_theme',
    ];

    $this->renderFile(
      'kumquat-theme/gitignore.twig',
      $defaultThemePath . '/.gitignore',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/gulpfile.js.twig',
      $defaultThemePath . '/gulpfile.js',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/info.yml.twig',
      $defaultThemePath . '/' . $defaultThemeParameters['machine_name'] . '.info.yml',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/libraries.yml.twig',
      $defaultThemePath . '/' . $defaultThemeParameters['machine_name'] . '.libraries.yml',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/theme.twig',
      $defaultThemePath . '/' . $defaultThemeParameters['machine_name'] . '.theme',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/package.json.twig',
      $defaultThemePath . '/package.json',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/readme.twig',
      $defaultThemePath . '/README.md',
      $defaultThemeParameters
    );

    $this->renderFile(
      'kumquat-theme/breakpoints.yml.twig',
      $defaultThemePath . '/' . $defaultThemeParameters['machine_name'] . '.breakpoints.yml',
      $defaultThemeParameters
    );

    // Copy the entire assets-src directory as we don't need any variable
    // replacement.
    $this->getFs()->mirror(self::TPL_DIR . '/kumquat-theme/assets-src', $defaultThemePath . '/assets-src');
    $this->trackGeneratedDirectory($defaultThemePath . '/assets-src');

    // Copy the entire templates directory as we don't need any variable
    // replacement.
    $this->getFs()->mirror(self::TPL_DIR . '/kumquat-theme/templates', $defaultThemePath . '/templates');
    $this->trackGeneratedDirectory($defaultThemePath . '/templates');

    // Copy the logo.
    $this->getFs()->copy(self::TPL_DIR . '/kumquat-theme/logo.svg', $defaultThemePath . '/logo.svg');
    $this->trackGeneratedFile($defaultThemePath . '/logo.svg');

    // Copy eslint config file.
    $this->getFs()->copy(self::TPL_DIR . '/kumquat-theme/.eslintrc.json', $defaultThemePath . '/.eslintrc.json');
    $this->trackGeneratedFile($defaultThemePath . '/.eslintrc.json');

    // Copy stylelint config file.
    $this->getFs()->copy(self::TPL_DIR . '/kumquat-theme/.stylelintrc.json', $defaultThemePath . '/.stylelintrc.json');
    $this->trackGeneratedFile($defaultThemePath . '/.stylelintrc.json');

    // Gitkeeps.
    $this->renderFile('kumquat-theme/gitkeep.twig', $defaultThemePath . '/assets-src/fonts/.gitkeep');

    $this->renderFile('kumquat-theme/gitkeep.twig', $defaultThemePath . '/dist/css/.gitkeep');
    $this->renderFile('kumquat-theme/gitkeep.twig', $defaultThemePath . '/dist/fonts/.gitkeep');
    $this->renderFile('kumquat-theme/gitkeep.twig', $defaultThemePath . '/dist/images/.gitkeep');
    $this->renderFile('kumquat-theme/gitkeep.twig', $defaultThemePath . '/dist/js/.gitkeep');

    // Safety.
    $this->renderFile('kumquat-theme/htaccess.deny.twig', $defaultThemePath . '/assets-src/.htaccess');

    // Blocks configuration.
    $config_folder = $parameters['config_folder'];
    $dir = opendir(self::TPL_DIR . '/kumquat-theme/config/blocks');
    while ($file = readdir($dir)) {
      if ($file[0] === '.') {
        continue;
      }

      $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
      $this->renderFile(
        'kumquat-theme/config/blocks/' . $file,
        $config_folder . '/block.block.' . $defaultThemeParameters['machine_name'] . '_' . $block_id . '.yml',
        $defaultThemeParameters
      );
    }

    // Add the theme path in the composer.json file.
    $prevDir = getcwd();
    chdir($this->drupalFinder->getComposerRoot());
    $enabledThemes = json_decode(exec('/usr/bin/env composer config extra.kumquat-themes'));

    $prefix = substr($this->drupalFinder->getDrupalRoot(), strlen($this->drupalFinder->getComposerRoot()));
    $prefix = trim($prefix, '/');
    $enabledThemes[] = $prefix . '/' . $defaultThemePath;

    exec('/usr/bin/env composer config extra.kumquat-themes --json \'' . json_encode(array_unique($enabledThemes)) . '\'');
    exec('/usr/bin/env composer update --lock');

    chdir($prevDir);
    $this->fileQueue->addFile('../composer.json');
    $this->countCodeLines->addCountCodeLines(1);

    if (count($enabledThemes) === 1 && $this->getFs()->exists('.lando.yml')) {
      $yaml = Yaml::decode(file_get_contents('.lando.yml'));
      $changed = 0;
      foreach ($yaml['tooling'] as &$settings) {
        if (isset($settings['dir']) && $settings['dir'] === '/app/web/themes/custom/kumquat_theme') {
          $settings['dir'] = '/app/web/themes/custom/' . $machine_name . '_theme';
          $changed++;
        }
      }
      file_put_contents('.lando.yml', Yaml::encode($yaml));

      $this->fileQueue->addFile('../.lando.yml');
      $this->countCodeLines->addCountCodeLines($changed);
    }
  }

  /**
   * Generates the configuration to enable the profile and themes by default.
   *
   * @param array $parameters
   *   The generation parameters.
   */
  public function generateConfig(array $parameters) {
    $machine_name = $parameters['machine_name'];
    $config_folder = $parameters['config_folder'];

    // Enable profile and themes in the core.extension.yml file.
    $filename = $config_folder . '/core.extension.yml';
    $config = $this->readConfig($filename);
    $current_profile = $config['profile'];

    if ($parameters['generate_core_module']) {
      $config['module'][$machine_name . '_core'] = 0;
    }
    if ($parameters['generate_profile']) {
      $config['module'][$machine_name] = 1000;
      unset($config['module'][$current_profile]);
      $config['profile'] = $machine_name;
    }
    $config['module'] = module_config_sort($config['module']);

    if ($parameters['generate_theme']) {
      $config['theme'][$machine_name . '_theme'] = 0;
      unset($config['theme']['bartik']);
    }
    if ($parameters['generate_admin_theme']) {
      /** @var \Drupal\Core\Extension\ThemeExtensionList $themeList */
      $themeList = \Drupal::service('extension.list.theme');
      if ($themeList->exists($parameters['base_admin_theme'])) {
        $baseThemes = $themeList->getBaseThemes($themeList->getList(), $parameters['base_admin_theme']);
        foreach (array_keys($baseThemes) as $baseThemeKey) {
          if (!isset($config['theme'][$baseThemeKey])) {
            $config['theme'][$baseThemeKey] = 0;
          }
        }
        $config['theme'][$parameters['base_admin_theme']] = 0;
      }
      $config['theme'][$machine_name . '_admin_theme'] = 0;
    }

    $this->writeConfig($filename, $config);

    // Set themes in the system.theme.yml file.
    $filename = $config_folder . '/system.theme.yml';
    $config = $this->readConfig($filename);

    if ($parameters['generate_admin_theme']) {
      $config['admin'] = $machine_name . '_admin_theme';
    }
    if ($parameters['generate_theme']) {
      $config['default'] = $machine_name . '_theme';
    }

    $this->writeConfig($filename, $config);

    // Set the generated profile name in combawa's install script if it's used
    // on the project.
    if ($parameters['generate_profile']) {
      $prevDir = getcwd();
      chdir($this->drupalFinder->getComposerRoot());

      $install_path = 'scripts/combawa/install.sh';
      if (file_exists($install_path)) {
        $install_script = file_get_contents($install_path);
        preg_match('/^\$DRUSH site-install.* (.*?)$/mi', $install_script, $matches);
        if (trim($matches[1], '\'" ') === 'minimal') {
          $install_script = preg_replace('/^\$DRUSH site-install(.*?) minimal\s*$/mi', '$DRUSH site-install $1 ' . $machine_name . "\n", $install_script);
          file_put_contents($install_path, $install_script);

          $this->fileQueue->addFile('../' . $install_path);
          $this->countCodeLines->addCountCodeLines(1);
        }
      }

      chdir($prevDir);
    }
  }

  /**
   * Track files generated without using a template.
   *
   * @param string $filename
   *   The generated file name.
   * @param int $current_lines
   *   The previous number of lines of the file.
   */
  protected function trackGeneratedFile($filename, $current_lines = 0) {
    $this->fileQueue->addFile($filename);
    $this->countCodeLines->addCountCodeLines(count(file($filename)) - $current_lines);
  }

  /**
   * Track directories generated without using templates.
   *
   * @param string $dirname
   *   The directory in which files has to be tracked.
   */
  protected function trackGeneratedDirectory($dirname) {
    $iterator = new \RecursiveDirectoryIterator($dirname);
    foreach (new \RecursiveIteratorIterator($iterator) as $file) {
      if (!in_array($file->getFilename(), ['.', '..'])) {
        $this->trackGeneratedFile($file->getPathname());
      }
    }
  }

  /**
   * Checks if a directory can be created or is writable.
   *
   * @param string $dir
   *   The directory to check.
   * @param string $type
   *   The type of directory checked (used for the message).
   *
   * @throws \RuntimeException
   */
  protected function checkDir($dir, $type) {
    if (file_exists($dir)) {
      if (!is_dir($dir)) {
        throw new \RuntimeException(
          sprintf(
            'Unable to generate the %s as the target directory "%s" exists but is a file.',
            $type,
            realpath($dir)
          )
        );
      }
      $files = scandir($dir);
      if ($files != ['.', '..']) {
        throw new \RuntimeException(
          sprintf(
            'Unable to generate the %s as the target directory "%s" is not empty.',
            $type,
            realpath($dir)
          )
        );
      }
      if (!is_writable($dir)) {
        throw new \RuntimeException(
          sprintf(
            'Unable to generate the %s as the target directory "%s" is not writable.',
            $type,
            realpath($dir)
          )
        );
      }
    }
  }

}
