<?php

 namespace mod_minilesson\Limelight\Parse\PartOfSpeech\Classes;

use mod_minilesson\Limelight\Parse\PartOfSpeech\PartOfSpeech;

class MeishiKazu implements PartOfSpeech
{
    /**
     * Handle the parsing request.
     *
     * @param array $properties
     * @param array $previousWord
     * @param array $previousToken
     * @param array $currentToken
     * @param array $nextToken
     * @return array
     */
    public function handle(
        array $properties,
        $previousWord,
        $previousToken,
        array $currentToken,
        $nextToken
    ) {
        $properties['partOfSpeech'] = 'number';

        return $properties;
    }
}
