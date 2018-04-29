<?php
/**
 * @author: kyussfia
 * @see:
 *
 * Created at: 2018.04.23. 16:48
 */

namespace PHPVisor\Internal\Configuration;


use PHPVisor\Internal\Options\AbstractOptions;

abstract class AbstractConfiguration
{
    /**
     * @var AbstractOptions
     */
    public $options;

    protected function loadFromFile(string $filePath, string $format = 'json')
    {
        switch ($format)
        {
            case 'json':
            default:
                $this->loadFromJson($filePath);
                break;
        }
    }

    abstract protected function loadFromJson(string $configPath);

    private function checkInDataArray(string $option, array $data)
    {
        if (isset($data[$option]) && null !== $data[$option] && "" !== $data[$option])
        {
            return $data[$option];
        }
        return null;
    }

    protected function loadOptionsFromData(array $options, array $data, AbstractOptions $loadInto)
    {
        foreach ($options as $optionName) {
            $fileData = $this->checkInDataArray($optionName, $data);
            if (null !== $fileData)
            {
                $loadInto->$optionName = $fileData;
            }
        }
    }
}