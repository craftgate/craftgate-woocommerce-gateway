<?php

namespace Craftgate;

use Craftgate\Adapter\PaymentAdapter;

class Craftgate
{
    private $options;

    public function __construct($options)
    {
        $this->setOptions($options);
    }

    public function setOptions($options)
    {
        if (is_array($options)) {
            $options = new CraftgateOptions($options);
        }

        if (!$options instanceof CraftgateOptions) {
            throw new \Exception(sprintf(
                'Argument $options must be either instance of %s or an array, %s given',
                'Craftgate\CraftgateOptions', gettype($options)
            ));
        }

        $this->options = $options;

        return $this;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function payment()
    {
        return new PaymentAdapter($this->options);
    }
}
