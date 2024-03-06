<?php

namespace KumquatScaffolder\Drush\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Serialization\Yaml;
use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Utils;
use DrupalCodeGenerator\Validator\Chained;
use DrupalCodeGenerator\Validator\Required;
use Drush\Attributes as CLI;

/**
 * Kumquat Scaffolder generate project command.
 */
class GenerateProjectDrushCommands extends DrushCommandsGeneratorBase {

  /**
   * Generate project command.
   */
  #[CLI\Command(name: 'kumquat:generate-project', aliases: ['kgp'])]
  #[CLI\Option(
    name: 'name',
    description: 'The project readable name.',
    suggestedValues: ['Happyculture', 'MyClient'])]
  #[CLI\Option(
    name: 'machine-name',
    description: 'The project (short) machine name.',
    suggestedValues: ['hc', 'my-client'])]
  #[CLI\Option(
    name: 'config-folder',
    description: 'The configuration storage folder, relative to the document root.',
    suggestedValues: ['../config/sync'])]
  #[CLI\Option(
    name: 'generate-all',
    description: 'Generate a new install profile, a core module, an admin theme, a front theme and the associated configuration. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-profile',
    description: 'Generate a new install profile. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-core-module',
    description: 'Generate a new core module. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-content-modules',
    description: 'Generate new content modules. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-theme',
    description: 'Generate a front theme. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-admin-theme',
    description: 'Generate an administration theme. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'generate-config',
    description: 'Change the config to use the new profile and themes by default. (Accepted: <info>boolean</info>)',
    suggestedValues: [TRUE, FALSE])]
  #[CLI\Option(
    name: 'base-admin-theme',
    description: 'The base theme of the administration theme.',
    suggestedValues: ['claro', 'gin'])]
  #[CLI\Usage(name: 'drush kumquat:generate-project', description: 'Run with wizard')]
  public function generateEnvironment(array $options = [
    'name' => self::REQ,
    'machine-name' => self::REQ,
    'config-folder' => self::REQ,
    'generate-all' => self::OPT,
    'generate-profile' => self::OPT,
    'generate-core-module' => self::OPT,
    'generate-content-modules' => self::OPT,
    'generate-theme' => self::OPT,
    'generate-admin-theme' => self::OPT,
    'generate-config' => self::OPT,
    'base-admin-theme' => self::REQ,
    'dry-run' => FALSE,
  ]): int {
    return $this->generate($options);
  }

  /**
   * {@inheritdoc}
   */
  protected function extractOptions(array $options): array {
    $vars = [
      'name' => $options['name'],
      'machine_name' => $options['machine-name'],
      'config_folder' => $options['config-folder'],
      'base_admin_theme' => $options['base-admin-theme'],
    ];
    return array_filter($vars, fn ($value) => !\is_null($value));
  }

  /**
   * {@inheritdoc}
   */
  protected function interview(array &$vars): void {
    $generator_parts = [
      'all',
      'profile',
      'core-module',
      'content-modules',
      'theme',
      'admin-theme',
      'config',
    ];

    $enabled_parts = [];
    foreach ($generator_parts as $part) {
      $enabled_parts[$part] = !empty($this->input()->getOption('generate-' . $part));
    }
    $enabled_parts = array_filter($enabled_parts);

    if (empty($enabled_parts)) {
      /** @var array $enabled_parts */
      $choices = $this->io()->choice(
        'What do you want to generate? Use comma separated values for multiple selection.',
        $generator_parts,
        0,
        TRUE
      );

      foreach ($generator_parts as $index => $part) {
        $enabled_parts[$part] = in_array($index, $choices);
        $this->input()->setOption('generate-' . $part, $enabled_parts[$part]);
      }
    }

    if (!isset($vars['name'])) {
      $composerData = Json::decode(file_get_contents($this->drupalFinder()->getComposerRoot() . '/composer.json'));
      [, $default_name] = explode('/', $composerData['name']);
      $vars['name'] = $this->io()->ask(
        'What is the human readable name of the project? (modules and theme names are derived from it)',
        ucwords($default_name),
        new Required()
      );
    }

    if (!isset($vars['machine_name'])) {
      $vars['machine_name'] = $this->io()->ask(
        'What is the machine name of the project? (modules and theme machine names are derived from it)',
        Utils::human2machine($vars['name']),
        new Chained(
          new Required(),
          static fn (string $value): string => static::validateMachineName($value),
        ),
      );
    }

    if ($enabled_parts['config'] || $enabled_parts['all']) {
      if (!isset($vars['config_folder'])) {
        $app_root = $this->drupalFinder()->getDrupalRoot();
        $vars['config_folder'] = $this->io()->ask(
          'Where are the configuration files stored (relative to the document root)?',
          '../config/sync',
          new Chained(
            new Required(),
            static fn (string $value): string => static::validatePath($value, $app_root),
          ),
        );
      }
    }

    if ($enabled_parts['admin-theme'] || $enabled_parts['all']) {
      if (!isset($vars['base_admin_theme'])) {
        $choices = ['adminimal_theme', 'gin', 'kumquat_gin'];
        $choice = $this->io()->choice(
          'Which theme you want your administration theme based on? (if you want another one, use the --base-admin-theme option)',
          $choices,
          'kumquat_gin'
        );
        $vars['base_admin_theme'] = $choices[$choice];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function validateVars(array $vars): void {
    if (isset($vars['machine_name'])) {
      static::validateMachineName($vars['machine_name']);
    }

    if (isset($vars['config_folder'])) {
      $app_root = $this->drupalFinder()->getDrupalRoot();
      static::validatePath($vars['config_folder'], $app_root);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getVarsSummary(array $vars): array {
    $generate_all = (bool) $this->input()->getOption('generate-all');
    $generate_profile = (bool) $this->input()->getOption('generate-profile');
    $generate_core_module = (bool) $this->input()->getOption('generate-core-module');
    $generate_content_modules = (bool) $this->input()->getOption('generate-content-modules');
    $generate_theme = (bool) $this->input()->getOption('generate-theme');
    $generate_admin_theme = (bool) $this->input()->getOption('generate-admin-theme');
    $generate_config = (bool) $this->input()->getOption('generate-config');

    // Improve attributes readibility.
    $recap_gen_profile = $generate_profile || $generate_all ? 'Yes' : 'No';
    $recap_gen_core_module = $generate_core_module || $generate_all ? 'Yes' : 'No';
    $recap_gen_content_modules = $generate_content_modules || $generate_all ? 'Yes' : 'No';
    $recap_gen_theme = $generate_theme || $generate_all ? 'Yes' : 'No';
    $recap_gen_admin_theme = $generate_admin_theme || $generate_all ? 'Yes' : 'No';
    $recap_gen_config = $generate_config || $generate_all ? 'Yes' : 'No';

    $summary = [
      'Name' => $vars['name'],
      'Machine name' => $vars['machine_name'],
    ];
    $summary['Generate profile'] = $recap_gen_profile;
    if ($generate_profile || $generate_all) {
      $summary['Profiles folder'] = static::PROFILES_FOLDER;
    }
    $summary['Generate core module'] = $recap_gen_core_module;
    $summary['Generate content modules'] = $recap_gen_content_modules;
    if ($generate_core_module || $generate_content_modules || $generate_all) {
      $summary['Modules folder'] = static::MODULES_FOLDER;
    }
    $summary['Generate front theme'] = $recap_gen_theme;
    $summary['Generate admin theme'] = $recap_gen_admin_theme;
    if ($generate_admin_theme || $generate_all) {
      $summary['Base admin theme'] = $vars['base_admin_theme'];
    }
    if ($generate_admin_theme || $generate_theme || $generate_all) {
      $summary['Themes folder'] = static::THEMES_FOLDER;
    }
    $summary['Generate config'] = $recap_gen_config;
    if ($generate_config || $generate_all) {
      $summary['Config folder'] = $vars['config_folder'];
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function postGenerate(array $vars): void {
    $generate_all = (bool) $this->input()->getOption('generate-all');
    $generate_profile = (bool) $this->input()->getOption('generate-profile');
    $generate_core_module = (bool) $this->input()->getOption('generate-core-module');
    $generate_theme = (bool) $this->input()->getOption('generate-theme');
    $generate_admin_theme = (bool) $this->input()->getOption('generate-admin-theme');
    $generate_config = (bool) $this->input()->getOption('generate-config');

    $root_folder = $this->drupalFinder()->getComposerRoot();
    $drupal_root = $this->drupalFinder()->getDrupalRoot();
    $machine_name = $vars['machine_name'];

    if ($generate_theme || $generate_all) {
      // Copy the entire assets-src directory as we don't need any variable
      // replacement.
      $this->fileSystem->mirror(
        static::TEMPLATES_PATH . '/kumquat-theme/assets-src',
        $drupal_root . '/' . static::THEMES_FOLDER . '/' . $machine_name . '_theme/assets-src'
      );
      $this->io()->note('Source assets have been copied to the generated theme.');

      // Copy the entire templates directory as we don't need any variable
      // replacement.
      $this->fileSystem->mirror(
        static::TEMPLATES_PATH . '/kumquat-theme/templates',
        $drupal_root . '/' . static::THEMES_FOLDER . '/' . $machine_name . '_theme/templates'
      );
      $this->io()->note('Default templates have been copied to the generated theme.');

      // Add the theme path in the composer.json file.
      $prevDir = getcwd();
      chdir($root_folder);
      $enabledThemes = json_decode(exec('/usr/bin/env composer config extra.kumquat-themes'));

      $prefix = substr($this->drupalFinder->getDrupalRoot(), strlen($this->drupalFinder->getComposerRoot()));
      $prefix = trim($prefix, '/');
      $enabledThemes[] = $prefix . '/' . static::THEMES_FOLDER . '/' . $machine_name . '_theme';

      exec('/usr/bin/env composer config extra.kumquat-themes --json \'' . json_encode(array_unique($enabledThemes)) . '\'');
      exec('/usr/bin/env composer update --lock');

      chdir($prevDir);
      $this->io()->note('composer files has been updated to add the generated theme path to extra configuration.');

      // Change the theme path in the .lando.yml file.
      $lando_path = $root_folder . '/.lando.yml';
      if (count($enabledThemes) === 1 && file_exists($lando_path)) {
        $yaml = Yaml::decode(file_get_contents($lando_path));
        foreach ($yaml['tooling'] as &$settings) {
          if (isset($settings['dir']) && $settings['dir'] === '/app/web/themes/custom/kumquat_theme') {
            $settings['dir'] = '/app/web/themes/custom/' . $machine_name . '_theme';
          }
        }
        $this->fileSystem->dumpFile($lando_path, Yaml::encode($yaml));
        $this->io()->note('.lando.yml file has been updated to use the generated theme path.');
      }
    }

    if ($generate_config || $generate_all) {
      // Enable profile and themes in the core.extension.yml file.
      $config_path = $this->drupalFinder()->getDrupalRoot() . '/' . $vars['config_folder'] . '/core.extension.yml';
      $config = Yaml::decode(file_get_contents($config_path));

      if ($generate_core_module || $generate_all) {
        $config['module'][$machine_name . '_core'] = 0;
      }
      if ($generate_profile || $generate_all) {
        $current_profile = $config['profile'];
        $config['module'][$machine_name] = 1000;
        unset($config['module'][$current_profile]);
        $config['profile'] = $machine_name;
      }
      // Drupal migh not be bootstrapped so we need to include this for the
      // module_config_sort() function to work.
      require_once $this->drupalFinder()->getDrupalRoot() . '/core/includes/module.inc';
      $config['module'] = module_config_sort($config['module']);

      if ($generate_theme || $generate_all) {
        $config['theme'][$machine_name . '_theme'] = 0;
        unset($config['theme']['olivero']);
      }
      if ($generate_admin_theme || $generate_all) {
        /** @var \Drupal\Core\Extension\ThemeExtensionList $themeList */
        $themeList = \Drupal::service('extension.list.theme');
        if ($themeList->exists($vars['base_admin_theme'])) {
          $baseThemes = $themeList->getBaseThemes($themeList->getList(), $vars['base_admin_theme']);
          foreach (array_keys($baseThemes) as $baseThemeKey) {
            if (!isset($config['theme'][$baseThemeKey])) {
              $config['theme'][$baseThemeKey] = 0;
            }
          }
          $config['theme'][$vars['base_admin_theme']] = 0;
        }
        $config['theme'][$machine_name . '_admin_theme'] = 0;
      }

      $this->fileSystem->dumpFile($config_path, Yaml::encode($config));
      $this->io()->note('core.extension.yml file has been updated to enable the generated modules, themes and profile.');

      // Set themes in the system.theme.yml file.
      $config_path = $this->drupalFinder()->getDrupalRoot() . '/' . $vars['config_folder'] . '/core.extension.yml';
      $config = Yaml::decode(file_get_contents($config_path));

      if ($generate_admin_theme || $generate_all) {
        $config['admin'] = $machine_name . '_admin_theme';
      }
      if ($generate_theme || $generate_all) {
        $config['default'] = $machine_name . '_theme';
      }

      $this->fileSystem->dumpFile($config_path, Yaml::encode($config));
      $this->io()->note('system.theme.yml file has been updated to use the generated themes.');
    }

    if ($generate_profile || $generate_all) {
      // Set the generated profile name in combawa's install script if it's used
      // on the project.
      $script_path = $root_folder . '/scripts/combawa/install.sh';
      if (file_exists($script_path)) {
        $install_script = file_get_contents($script_path);
        preg_match('/^\$DRUSH site-install.* (.*?)$/mi', $install_script, $matches);
        if (trim($matches[1], '\'" ') === 'minimal') {
          $install_script = preg_replace('/^\$DRUSH site-install(.*?) minimal\s*$/mi', '$DRUSH site-install $1 ' . $machine_name . "\n", $install_script);
          $this->fileSystem->dumpFile($script_path, $install_script);
        }
        $this->io()->note('scripts/combawa/install.sh file has been updated to use the generated profile.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function collectAssets(AssetCollection $assets, array $vars): void {
    $generate_all = (bool) $this->input()->getOption('generate-all');
    $generate_profile = (bool) $this->input()->getOption('generate-profile');
    $generate_core_module = (bool) $this->input()->getOption('generate-core-module');
    $generate_content_modules = (bool) $this->input()->getOption('generate-content-modules');
    $generate_theme = (bool) $this->input()->getOption('generate-theme');
    $generate_admin_theme = (bool) $this->input()->getOption('generate-admin-theme');
    $generate_config = (bool) $this->input()->getOption('generate-config');

    if ($generate_profile || $generate_all) {
      $this->collectAssetsProfile($assets, $vars);
    }
    if ($generate_core_module || $generate_all) {
      $this->collectAssetsCoreModule($assets, $vars);
    }
    if ($generate_content_modules || $generate_all) {
      $this->collectAssetsContentModules($assets, $vars);
    }
    if ($generate_admin_theme || $generate_all) {
      $this->collectAssetsAdminTheme($assets, $vars);
    }
    if ($generate_theme || $generate_all) {
      $this->collectAssetsTheme($assets, $vars);
    }
    if ($generate_config || $generate_all) {
      $this->collectAssetsConfig($assets, $vars);
    }
  }

  /**
   * Collect profile generation assets.
   */
  protected function collectAssetsProfile(AssetCollection $assets, array $vars): void {
    $machine_name = $vars['machine_name'];
    $baseDir = static::PROFILES_FOLDER . '/' . $machine_name . '/';
    $assets->addFile(
      $baseDir . $machine_name . '.info.yml',
      'kumquat-profile/info.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.profile',
      'kumquat-profile/profile.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.install',
      'kumquat-profile/install.twig',
    );
  }

  /**
   * Collect core module generation assets.
   */
  protected function collectAssetsCoreModule(AssetCollection $assets, array $vars): void {
    $machine_name = $vars['machine_name'] . '_core';
    $baseDir = static::MODULES_FOLDER . '/' . $machine_name . '/';
    $assets->addFile(
      $baseDir . $machine_name . '.info.yml',
      'kumquat-core-module/info.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.module',
      'kumquat-core-module/module.twig',
    );
  }

  /**
   * Collect content modules generation assets.
   */
  protected function collectAssetsContentModules(AssetCollection $assets, array $vars): void {
    foreach (['deploy', 'test'] as $mode) {
      $machine_name = $vars['machine_name'] . '_content_' . $mode;
      $baseDir = static::MODULES_FOLDER . '/' . $machine_name . '/';
      $assets->addFile(
        $baseDir . $machine_name . '.info.yml',
        'kumquat-content-modules/info.yml.twig',
      );
      $assets->addFile(
        $baseDir . 'content/.gitkeep',
      );
    }
  }

  /**
   * Collect admin theme generation assets.
   */
  protected function collectAssetsAdminTheme(AssetCollection $assets, array $vars): void {
    $machine_name = $vars['machine_name'] . '_admin_theme';
    $baseDir = static::THEMES_FOLDER . '/' . $machine_name . '/';
    $assets->addFile(
      $baseDir . $machine_name . '.info.yml',
      'kumquat-admin-theme/info.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.theme',
      'kumquat-admin-theme/theme.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.libraries.yml',
      'kumquat-admin-theme/libraries.yml.twig',
    );
    $assets->addFile(
      $baseDir . 'css/' . $machine_name . '.css',
      'kumquat-admin-theme/base.css.twig',
    );

    if (in_array($vars['base_admin_theme'], ['gin', 'kumquat_gin'])) {
      $assets->addFile(
        $baseDir . 'config/install/' . $machine_name . '.settings.yml',
        'kumquat-admin-theme/admin_theme.settings.yml.twig',
      );
    }

    // Blocks configuration.
    $dir = opendir(static::TEMPLATES_PATH . '/kumquat-admin-theme/config/blocks');
    while ($file = readdir($dir)) {
      if ($file[0] === '.') {
        continue;
      }

      $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
      $assets->addFile(
        $baseDir . 'config/install/block.block.' . $machine_name . '__' . $block_id . '.yml',
        'kumquat-admin-theme/config/blocks/' . $file,
      );
    }
  }

  /**
   * Collect default theme generation assets.
   */
  protected function collectAssetsTheme(AssetCollection $assets, array $vars): void {
    $machine_name = $vars['machine_name'] . '_theme';
    $baseDir = static::THEMES_FOLDER . '/' . $machine_name . '/';

    // Drupal files.
    $assets->addFile(
      $baseDir . $machine_name . '.info.yml',
      'kumquat-theme/info.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.breakpoints.yml',
      'kumquat-theme/breakpoints.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.libraries.yml',
      'kumquat-theme/libraries.yml.twig',
    );
    $assets->addFile(
      $baseDir . $machine_name . '.theme',
      'kumquat-theme/theme.twig',
    );
    $assets->addFile(
      $baseDir . 'logo.svg',
      'kumquat-theme/logo.svg',
    );

    // Build files.
    $assets->addFile(
      $baseDir . 'README.md',
      'kumquat-theme/readme.twig',
    );
    $assets->addFile(
      $baseDir . '.gitignore',
      'kumquat-theme/gitignore.twig',
    );
    $assets->addFile(
      $baseDir . 'gulpfile.js',
      'kumquat-theme/gulpfile.js.twig',
    );
    $assets->addFile(
      $baseDir . 'package.json',
      'kumquat-theme/package.json.twig',
    );
    $assets->addFile(
      $baseDir . '.eslintrc.json',
      'kumquat-theme/.eslintrc.json',
    );
    $assets->addFile(
      $baseDir . '.stylelintrc.json',
      'kumquat-theme/.stylelintrc.json',
    );

    // Gitkeeps.
    $assets->addFile($baseDir . 'assets-src/fonts/.gitkeep');
    $assets->addFile($baseDir . 'dist/css/.gitkeep');
    $assets->addFile($baseDir . 'dist/fonts/.gitkeep');
    $assets->addFile($baseDir . 'dist/images/.gitkeep');
    $assets->addFile($baseDir . 'dist/js/.gitkeep');

    // Safety.
    $assets->addFile(
      $baseDir . 'assets-src/.htaccess',
      'kumquat-theme/htaccess.deny.twig',
    );

    // Blocks configuration.
    $dir = opendir(static::TEMPLATES_PATH . '/kumquat-theme/config/blocks');
    while ($file = readdir($dir)) {
      if ($file[0] === '.') {
        continue;
      }

      $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
      $assets->addFile(
        $baseDir . 'config/install/block.block.' . $machine_name . '__' . $block_id . '.yml',
        'kumquat-theme/config/blocks/' . $file,
      );
    }
  }

  /**
   * Collect config generation assets.
   */
  protected function collectAssetsConfig(AssetCollection $assets, array $vars): void {
    $generate_all = (bool) $this->input()->getOption('generate-all');
    $generate_theme = (bool) $this->input()->getOption('generate-theme');
    $generate_admin_theme = (bool) $this->input()->getOption('generate-admin-theme');

    if ($generate_admin_theme || $generate_all) {
      // Admin Theme blocks.
      $dir = opendir(static::TEMPLATES_PATH . '/kumquat-admin-theme/config/blocks');
      while ($file = readdir($dir)) {
        if ($file[0] === '.') {
          continue;
        }

        $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
        $assets->addFile(
          $vars['config_folder'] . '/block.block.' . $vars['machine_name'] . '_admin_theme__' . $block_id . '.yml',
          'kumquat-admin-theme/config/blocks/' . $file,
        );
      }

      // Admin Theme settings.
      $assets->addFile(
        $vars['config_folder'] . '/block.block.' . $vars['machine_name'] . '_admin_theme.settings.yml',
        'kumquat-admin-theme/admin_theme.settings.yml.twig',
      );
    }

    if ($generate_theme || $generate_all) {
      // Default Theme blocks.
      $dir = opendir(static::TEMPLATES_PATH . '/kumquat-theme/config/blocks');
      while ($file = readdir($dir)) {
        if ($file[0] === '.') {
          continue;
        }

        $block_id = substr($file, 0, -1 * strlen('.yml.twig'));
        $assets->addFile(
          $vars['config_folder'] . '/block.block.' . $vars['machine_name'] . '_theme__' . $block_id . '.yml',
          'kumquat-theme/config/blocks/' . $file,
        );
      }
    }
  }

}
