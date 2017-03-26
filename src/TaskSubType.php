<?php

namespace Droath\ProjectX;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\IO;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\LoadAllTasks;

/**
 * Define the Project-X task subtype class.
 */
abstract class TaskSubType implements BuilderAwareInterface, ContainerAwareInterface, IOAwareInterface, ProjectXAwareInterface
{
    use IO;
    use LoadAllTasks;
    use ContainerAwareTrait;
    use ProjectXAwareTrait;

    /**
     * Get console application.
     *
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication()
    {
        return $this->getContainer()->get('application');
    }

    /**
     * Template manager instance.
     *
     * @return \Droath\ProjectX\Template\TemplateManager
     */
    protected function templateManager()
    {
        return $this->getContainer()->get('projectXTemplate');
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
     * Copy template file to project root.
     *
     * @param string $filename
     *   The filename of template file.
     * @param bool $overwrite
     *   A flag to determine if the file should be overwritten if exists.
     */
    protected function copyTemplateFileToProject($filename, $overwrite = false)
    {
        $this->copyTemplateFilesToProject([$filename => $filename], $overwrite);
    }

    /**
     * Copy template files to project root.
     *
     * @param array $filenames
     *   An array of template filenames, keyed by target path.
     * @param bool $overwrite
     *   A flag to determine if the file should be overwritten if exists.
     */
    protected function copyTemplateFilesToProject(array $filenames, $overwrite = false)
    {
        try {
            $filesystem = $this->taskFilesystemStack();
            foreach ($filenames as $template_path => $target_path) {
                $target_file = $this
                    ->getProjectXRootPath() . "/{$target_path}";

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
    }
}
