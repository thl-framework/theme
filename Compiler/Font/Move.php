<?php

namespace  Mods\Theme\Compiler\Font;

use Mods\Theme\Compiler\Base\Move as BaseMove;

class Move extends BaseMove
{
    /**
     * Hold the type of asset need to be moved.
     *
     * @var string
     */
    protected $type = 'fonts';

    /**
     * Check if the assets can be moved.
     *
     * @param Array $manifest
     * @return bool
     */
    protected function canMove($manifest)
    {
        return true;
    }
}
