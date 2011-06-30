<?php

namespace Knplabs\Snappy;

/**
 * Base class for Snappy Media
 *
 * @package Snappy
 *
 * @author  Matthieu Bontemps <matthieu.bontemps@knplabs.com>
 * @author  Antoine Hérault <antoine.herault@knplabs.com>
 */
abstract class Media
{
    private $binary;
    private $options;

    /**
	 * Constructor
     *
     * @param  string $binary
     * @param  array  $options
     */
    public function __construct($binary, array $options)
    {
        $this->configure();

        $this->setBinary($binary);
        $this->setOptions($options);
    }

    /**
     * This method must configure the media options
     *
     * @see Media::addOption()
     */
    abstract protected function configure();

    /**
     * Adds an option
     *
     * @param  string $name    The name
     * @param  mixed  $default An optional default value
     */
    protected function addOption($name, $default = null)
    {
        $this->options[$name] = $default;
    }

    /**
     * Adds an array of options
     *
     * @param  array $options
     */
    protected function addOptions(array $options)
    {
        foreach ($options as $name => $default) {
            $this->addOption($name, $default);
        }
    }

    /**
	 * Sets an option. Be aware that option values are NOT validated and that
	 * it is your responsibility to validate user inputs
     *
     * @param  string $name  The option to set
     * @param  mixed  $value The value (NULL to unset)
     */
    public function setOption($name, $value)
    {
        if (!array_key_exists($option, $this->options)) {
            throw new \InvalidArgumentException(sprintf('The option \'%s\' does not exist.', $option));
        }

        $this->options[$option] = $value;
    }

    /**
     * Sets an array of options
     *
     * @param  array $options An associative array of options as name/value
     */
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * Returns the content of a media
     *
     * @param  string $url Url of the page
	 *
     * @return string
     */
    public function getOutput($input)
    {
        $file = tempnam(sys_get_temp_dir(), 'knplabs_snappy');
        $this->unlink($file);

        $this->convert($input, $file);

        return file_get_contents($file);
    }

    /**
     * Converts the input HTML file into the output one
     *
     * @param  string $input     The input filename
     * @param  string $output    The output filename
     * @param  string $overwrite Whether to overwrite the output file if it
     *                           already exist
     */
    public function convert($input, $output, $overwrite = false)
    {
        if (null === $this->binary) {
            throw new \LogicException(
                'You must define a binary prior to conversion.'
            );
        }

        $this->prepareOutput($output, $overwrite);

        $this->executeCommand($this->getCommand($input, $output));

        // todo manage the conversion error output. Currently, we simply do a
        // small diagnostic of the file after the conversion

        if (!$this->fileExists($output)) {
            throw new \RuntimeException(sprintf(
                'The file \'%s\' was not created.', $output
            ));
        }

        if (0 === $this->filesize($output)) {
            throw new \RuntimeException(sprintf(
                'The file \'%s\' was created but is empty.', $output
            ));
        }
    }

    /**
     * Converts the given HTML into the output file
     *
     * @param  string $html   The HTML content to convert
     * @param  string $output The ouput filename
     */
    public function convertHtml($html, $output)
    {
        $filename = $this->createTemporaryFile($html);

        return $this->convert($filename, $output);
    }

    /**
	 * Defines the binary
     *
     * @param  string $binary The path/name of the binary
     */
    public function setBinary($binary)
    {
        $this->binary = $binary;
    }

    /**
     * Returns the command for the given input and output files
     *
     * @param  string $input  The input file
     * @param  string $output The ouput file
     */
    public function getCommand($input, $output)
    {
        return $this->buildCommand($this->binary, $input, $output, $this->options);
    }

    /**
     * Builds the command string
     *
	 * @param  string $binary	The binary path/name
     * @param  string $input    Url or file location of the page to process
     * @param  string $output   File location to the image-to-be
	 * @param  array  $options 	An array of options
	 *
     * @return string
     */
    private function buildCommand($binary, $input, $output, array $options)
    {
        $command = $binary;

        foreach ($options as $key => $value) {
            if (null !== $value && false !== $value) {
                if (true === $value) {
                    $command .= " --".$key;
                } elseif (is_array($value)) {
                    foreach ($value as $v) {
                        $command .= " --".$key." ".$v;
                    }
                } else {
                    $command .= " --".$key." ".$value;
                }
            }
        }

        $command .= " \"$input\" \"$output\"";

        return $command;
    }

	/**
	 * Executes the given command via shell and returns the complete output as
	 * a string
	 *
	 * @param  string $command
	 *
	 * @return string
	 */
    private function executeCommand($command)
    {
        return shell_exec($command);
    }

    /**
     * Prepares the specified output
     *
     * @param  string  $filename  The output filename
     * @param  boolean $overwrite Whether to overwrite the file if it already
     *                            exist
     */
    private function prepareOutput($filename, $overwrite)
    {
        $directory = dirname($filename);

        if ($this->fileExists($filename)) {
            if (!$this->isFile($filename)) {
                throw new \InvalidArgumentException(sprintf(
                    'The output file \'%s\' already exists and it is a %s.',
                    $filename, $this->isDir($filename) ? 'directory' : 'link'
                ));
            } elseif (false === $overwrite) {
                throw new \InvalidArgumentException(sprintf(
                    'The output file \'%s\' already exists.',
                    $filename
                ));
            } elseif (!$this->unlink($filename)) {
                throw new \RuntimeException(sprintf(
                    'Could not delete already existing output file \'%s\'.',
                    $filename
                ));
            }
        } elseif (!$this->isDir($directory) && !$this->mkdir($directory)) {
            throw new \RuntimeException(sprintf(
                'The output file\'s directory \'%s\' could not be created.',
                $directory
            ));
        }
    }

    /**
     * Wrapper for the "file_exists" function
     *
     * @param  string $filename
     *
     * @return boolean
     */
    private function fileExists($filename)
    {
        return file_exists($filename);
    }

    /**
     * Wrapper for the "is_file" method
     *
     * @param  string $filename
     *
     * @return boolean
     */
    private function isFile($filename)
    {
        return is_file($filename);
    }

    /**
     * Wrapper for the "filesize" function
     *
     * @param  string $filename
     *
     * @return integer or FALSE on failure
     */
    private function filesize($filename)
    {
        return filesize($filename);
    }

    /**
     * Wrapper for the "unlink" function
     *
     * @param  string $filename
     *
     * @return boolean
     */
    private function unlink($filename)
    {
        return unlink($filename);
    }

    /**
     * Wrapper for the "is_dir" function
     *
     * @param  string $filename
     *
     * @return boolean
     */
    private function isDir($filename)
    {
        return is_dir($filename);
    }

    /**
     * Wrapper for the mkdir function
     *
     * @param  string $pathname
     *
     * @return boolean
     */
    private function mkdir($pathname)
    {
        return mkdir($pathname, 0777, true);
    }
}
