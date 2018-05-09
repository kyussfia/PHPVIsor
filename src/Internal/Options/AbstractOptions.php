<?php
/**
 * @author: kyussfia
 * @see: https://github.com/kyussfia/PHPVisor
 *
 * Created at: 2018.04.07. 0:01
 */

namespace PHPVisor\Internal\Options;

abstract class AbstractOptions
{
    protected $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    protected function setOptionIfExist($option, $opts)
    {
        if (!is_array($opts))
        {
            $opts = array($opts);
        }
        foreach ($opts as $opt)
        {
            if (isset($this->options[$opt]))
            {
                $this->$option = $this->options[$opt];
                return;
            }
        }
    }

    protected function setBoolOptionIfExist($option, $optA, $optB)
    {
        if (isset($this->options[$optA]) || isset($this->options[$optB]))
        {
            $this->$option = true;
        }
    }

    protected function convertSizeToInt(string $size) : int
    {
        $aUnits = array('B'=>0, 'KB'=>1, 'MB'=>2, 'GB'=>3, 'TB'=>4, 'PB'=>5, 'EB'=>6, 'ZB'=>7, 'YB'=>8);
        $matches = array();
        $found = preg_match('(' . implode('|', array_keys($aUnits)) . ')i', $size,$matches, PREG_OFFSET_CAPTURE);
        if (!$found)
        {
            return (int)$size;
        }
        $sUnit = strtoupper(trim($matches[0][0]));
        if (!in_array($sUnit, array_keys($aUnits))) {
            return false;
        }
        $iUnits = trim(substr($size, 0, $matches[0][1]));
        if (!intval($iUnits) == $iUnits) {
            return false;
        }
        return $iUnits * pow(1024, $aUnits[$sUnit]);
    }
}