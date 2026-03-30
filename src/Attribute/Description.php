<?php

namespace Gdnacho\Poob\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Description
{
    public function __construct(
        public string $text
    ) {}
}