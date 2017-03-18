<?php

namespace Droath\ProjectX\Project;

use Boedah\Robo\Task\Drush\loadTasks;
use Droath\ProjectX\ProjectX;

/**
 * Define Drupal project type.
 */
class DrupalProjectType extends PhpProjectType
{
    use loadTasks;

    const BUILD_FRESH = 'build:fresh';
    const BUILD_DIRTY = 'build:dirty';
    const BUILD_ABORT = 'build:abort';

    /**
     * Constructor for the Drupal project type.
     */
    public function __construct()
    {
        $this->supportsDocker();
    }

    /**
     * {@inheritdoc}.
     */
    public function build()
    {
        $status = $this->startBuild();

        if ($status === static::BUILD_ABORT) {
            return;
        } elseif ($status === static::BUILD_DIRTY) {
            $this->removeInstallRoot();
        } elseif ($status === static::BUILD_FRESH) {
            $this->updateProjectComposer();
        }
        parent::build();

        // Create application install root directory.
        $this->taskFilesystemStack()
            ->chmod($this->getProjectXRootPath(), 0775)
            ->mkdir($this->getInstallPath(), 0775)
            ->run();

        // Run composer update within the project root.
        $this->taskComposerUpdate()
            ->run();
    }

    /**
     * {@inheritdoc}.
     */
    public function install()
    {
        $status = $this->startInstall();

        if (!$status) {
            return;
        }
        $this->say("Drupal's install process has begun. 🤘");

        $options = $this->getInstallOptions();
        $install_path = $this->getInstallPath();

        $sites = "{$install_path}/sites";
        $settings = "{$sites}/default/settings.php";
        $settings_local = "{$sites}/default/settings.local.php";

        // Change permission, create default files directory, copy settings.php,
        // create local settings.
        $this->taskFilesystemStack()
            ->chmod($sites, 0775, 0000, true)
            ->mkdir("{$sites}/default/files")
            ->copy("{$sites}/default/default.settings.php", $settings)
            ->copy("{$sites}/example.settings.local.php", $settings_local)
            ->run();

        // Start project environment.
        $this->taskSymfonyCommand($this->getAppCommand('engine:up'))
            ->run();

        // Append configurations into default local settings.
        $settings_local_text = $this
            ->templateManager()
            ->loadTemplate('settings.local.txt', 'none');

        $this->taskWriteToFile($settings_local)
            ->append()
            ->appendUnlessMatches('/\$databases\[.+\]/', $settings_local_text)
            ->run();

        $this->say('Waiting on project engine to become available...');

        $db_host = '127.0.0.1';
        $db_connection = $this->hasDatabaseConnection($db_host);

        if (!$db_connection) {
            throw new \Exception(
                sprintf('Unable to connection to engine database %s', $db_host)
            );
        }

        // Sometimes it takes awhile after the mysql host is up on the network
        // to become totally available to except connections. Due to the
        // uncertainty we'll need to sleep for about 30 seconds.
        sleep(30);

        // Run Drupal site install via drush.
        $this->taskDrushStack()
            ->drupalRootDirectory($install_path)
            ->siteName($options['site']['name'])
            ->accountMail($options['account']['mail'])
            ->accountName($options['account']['name'])
            ->accountPass($options['account']['pass'])
            ->mysqlDbUrl("admin:root@$db_host:3306/drupal")
            ->siteInstall($options['site']['profile'])
            ->run();

        // Update permissions to ensure all files can be accessed on the
        // install path for both user and groups.
        $this->_chmod($install_path, 0775, 0000, true);

        $settings_include = $this
            ->templateManager()
            ->loadTemplate('settings.txt', 'none');

        // Append settings.local include into sites/default/settings.php.
        $this->taskWriteToFile($settings)
            ->append()
            ->appendUnlessMatches(
                "/(include.+\/settings.local.php\'\;)\n\}/",
                $settings_include
            )
            ->run();

        // Open project site in browser.
        sleep(10);
        $this->taskOpenBrowser('http://localhost')
            ->run();
    }

    /**
     * {@inheritdoc}
     */
    public function onEngineUp()
    {
        $this->say("Setting Drupal's default/files directory permissions. 🤘");
        $install_path = $this->getInstallPath();
        $files = "{$install_path}/sites/default/files";

        // Ensure default/files permissions are 0775 on project launch.
        $this->_chmod($files, 0775, 0000, true);
    }

    /**
     * Check if host has database connection.
     *
     * @param string $host
     *   The database hostname.
     * @param int $port
     *   The database port.
     * @param int $seconds
     *   The amount of seconds to continually check.
     *
     * @return bool
     *   Return true if the database is connectible; otherwise false.
     */
    protected function hasDatabaseConnection($host, $port = 3306, $seconds = 30)
    {
        $hostChecker = $this->getHostChecker();
        $hostChecker
            ->setHost($host)
            ->setPort($port);

        return $hostChecker->isPortOpenRepeater($seconds);
    }

    /**
     * Get Drupal install options.
     */
    protected function getInstallOptions()
    {
        $config = $this->getProjectXConfig();

        $options = isset($config['options']['drupal'])
            ? $config['options']['drupal']
            : [];

        return array_replace_recursive(
            $this->defaultInstallOptions(),
            $options
        );
    }

    /**
     * Get default Drupal install options.
     */
    protected function defaultInstallOptions()
    {
        $config = $this->getProjectXConfig();

        return [
            'site' => [
                'name' => $config['name'],
                'profile' => 'standard',
            ],
            'account' => [
                'mail' => 'admin@example.com',
                'name' => 'admin',
                'pass' => 'admin',
            ],
        ];
    }

    /**
     * Install docker configurations that are specific to Drupal.
     *
     * @param bool $use_docker_sync
     *   Use docker sync.
     */
    public function dockerInstall($use_docker_sync)
    {
        $project_root = $this->getProjectXRootPath();
        $docker_root = $project_root . '/docker';

        $this->taskfilesystemStack()
            ->mkdir($docker_root)
            ->mirror($this->getTemplateFilePath('docker/mysql'), "{$docker_root}/mysql")
            ->mirror($this->getTemplateFilePath('docker/nginx'), "{$docker_root}/nginx")
            ->mirror($this->getTemplateFilePath('docker/php-fpm'), "{$docker_root}/php-fpm")
            ->copy($this->getTemplateFilePath('docker/docker-compose.yml'), "{$project_root}/docker-compose.yml")
            ->run();

        if ($use_docker_sync) {
            $this->copyTemplateFilesToProject([
                'docker/docker-sync.yml' => 'docker-sync.yml',
                'docker/docker-compose-dev.yml' => 'docker-compose-dev.yml',
            ]);

            // Update the docker compose development configuration to replace
            // the placeholder variables with the valid host IP.
            $this->taskWriteToFile("$project_root/docker-compose-dev.yml")
                ->append()
                ->place('HOST_IP_ADDRESS', ProjectX::clientHostIP())
                ->run();

            $project_name = $this->getApplication()
                ->getProjectMachineName();

            $sync_name = uniqid("$project_name-", false);

            // Set the docker sync .env file with the sync name defined.
            $this->taskWriteToFile("{$project_root}/.env")
                ->append()
                ->appendUnlessMatches('/SYNC_NAME=\w+/', "SYNC_NAME=$sync_name")
                ->run();
        }
    }

    /**
     * Start the Drupal build.
     */
    protected function startBuild()
    {
        $rebuild = false;

        if ($this->isBuilt()) {
            $rebuild = $this->confirm(
                'Drupal has been built already, do you want to rebuild?'
            );

            if (!$rebuild) {
                $this->say("Drupal's build process has been aborted! ⛈️");

                return static::BUILD_ABORT;
            }
        }
        $this->say("Drupal's build process has begun. 🤘");

        return !$rebuild ? static::BUILD_FRESH : static::BUILD_DIRTY;
    }

    /**
     * Start the Drupal install.
     */
    protected function startInstall()
    {
        if (!$this->isBuilt()) {
            $this->say(
                "Unable to install Drupal as the project hasn't been built yet. ⛈️"
            );

            return false;
        }

        return true;
    }

    /**
     * Get composer template file.
     *
     * @return string
     */
    protected function getComposerTemplate()
    {
        $template = 'composer.json';

        if ($this->useBlt()) {
            $template = 'blt.composer.json';
        }

        return $template;
    }

    /**
     * Has Drupal been built.
     *
     * @return bool
     */
    protected function isBuilt()
    {
        $project_root = dirname(
            $this->findProjectXConfigPath()
        );

        // Check if Drupal install root exist and is not empty.
        return is_dir($project_root . static::INSTALL_ROOT)
            && (new \FilesystemIterator($project_root . static::INSTALL_ROOT))->valid();
    }

    /**
     * Use Acquia BLT.
     */
    protected function useBlt()
    {
        return $this->confirm('Use Acquia BLT?');
    }

    /**
     * Update project composer.json.
     */
    protected function updateProjectComposer()
    {
        $template = $this->getComposerTemplate();
        $this->composer()
            ->mergeWithTemplate($template)
            ->update();

        return $this;
    }

    /**
     * Remove project install root.
     */
    protected function removeInstallRoot()
    {
        $this->taskDeleteDir($this->getInstallPath())->run();

        return $this;
    }

    /**
     * Get project install path.
     *
     * @return string
     *   The project install path.
     */
    protected function getInstallPath()
    {
        return $this->getProjectXRootPath() . static::INSTALL_ROOT;
    }
}
