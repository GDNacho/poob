<?php

namespace Gdnacho\Poob\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Summary
{
    public function __construct(
        public string $text
    ) {}
}