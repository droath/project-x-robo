<?php

namespace Droath\ProjectX;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\IO;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\LoadAllTasks;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Define the Project-X task subtype class.
 */
abstract class TaskSubType implements BuilderAwareInterface, ContainerAwareInterface, IOAwareInterface
{
    use IO;
    use LoadAllTasks;
    use ContainerAwareTrait;
    use TaskResultTrait;

    /**
     * Sub-type task directories.
     *
     * @return array
     *   An array of sub-type task directories.
     */
    public function taskDirectories()
    {
        return [];
    }

    /**
     * Template directories.
     *
     * @return array
     *   An array of directories to search for template files.
     */
    public function templateDirectories()
    {
        return [];
    }

    /**
     * Get project-x configurations.
     *
     * @return \Droath\ProjectX\Config\ProjectXConfig
     */
    protected function getConfigs()
    {
        return ProjectX::getProjectConfig();
    }

    /**
     * Get console application.
     *
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication()
    {
        return $this->getContainer()
            ->get('application');
    }

    /**
     * Template manager instance.
     *
     * @return \Droath\ProjectX\Template\TemplateManager
     */
    protected function templateManager()
    {
        return $this->getContainer()
            ->get('projectXTemplate')
            ->setSearchDirectories($this->templateDirectories());
    }

    /**
     * Get template directory path for the given file.
     *
     * @return string
     *   The full path to the template file.
     */
    protected function getTemplateFilePath($filename)
    {
        return $this->templateManager()->getTemplateFilePath($filename);
    }

    /**
     * Load template contents.
     *
     * @param string $filename
     *
     * @return string|array
     *   The templates raw contents; if format was provided the decoded contents
     *   is returned.
     */
    protected function loadTemplateContents($filename, $format = null)
    {
        return $this->templateManager()->loadTemplate($filename, $format);
    }

    /**
     * Copy template file to project root.
     *
     * @param string $filename
     *   The filename of template file.
     * @param bool $overwrite
     *   A flag to determine if the file should be overwritten if exists.
     *
     * @return TaskSubType
     * @throws \Exception
     */
    protected function copyTemplateFileToProject($filename, $overwrite = false)
    {
        return $this->copyTemplateFilesToProject([$filename => $filename], $overwrite);
    }

    /**
     * Copy template files to project root.
     *
     * @param array $filenames
     *   An array of template filenames, keyed by target path.
     * @param bool $overwrite
     *   A flag to determine if the file should be overwritten if exists.
     *
     * @return self
     * @throws \Exception
     */
    protected function copyTemplateFilesToProject(array $filenames, $overwrite = false)
    {
        try {
            $filesystem = $this->taskFilesystemStack();
            foreach ($filenames as $template_path => $target_path) {
                $target_file = ProjectX::projectRoot() . "/{$target_path}";

                $template_file = $this
                    ->templateManager()
                    ->getTemplateFilePath($template_path);

                $filesystem->copy($template_file, $target_file, $overwrite);
            }
            $filesystem->run();
        } catch (\Exception $e) {
            throw new \Exception(
                sprintf('Failed to copy template file(s) into project!')
            );
        }

        return $this;
    }

    /**
     * Ask confirmation question.
     *
     * @param string $text
     *   The question text.
     * @param bool $default
     *   The default value.
     *
     * @return bool
     */
    protected function askConfirmQuestion($text, $default = false)
    {
        $default_text = $default ? 'yes' : 'no';
        $question = "☝️  $text (y/n) [$default_text] ";

        return $this->doAsk(new ConfirmationQuestion($question, $default));
    }
}
