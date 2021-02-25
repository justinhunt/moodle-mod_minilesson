<?php

 namespace mod_minilesson\Limelight\Helpers\Contracts;

interface Convertable
{
    /**
     * Convert the instance items to format.
     *
     * @param string $format
     * @return mixed
     */
    public function convert($format);
}
