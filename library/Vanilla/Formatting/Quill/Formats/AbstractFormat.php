<?php
/**
 * @author Adam (charrondev) Charron <adam.c@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license https://opensource.org/licenses/GPL-2.0 GPL-2.0
 */

namespace Vanilla\Formatting\Quill\Formats;

use Vanilla\Formatting\Quill\Blots\TextBlot;

/**
 * An different type of blot that can contain nested blots inside. Multiple of these can be active at the same time.
 *
 * @see TextBlot Usage of these blots can be found in the TextBlot class.
 */
abstract class AbstractFormat {

    /**
     * @var array The primary operation of the Format. Used to determine the primary tags.
     */
    protected $currentOperation = [];

    /**
     * @var array The previous operation. Used to optimize opening tags.
     */
    protected $previousOperation = [];

    /**
     * @var array The next operation from the currentOperation. Used to optimize closing tags.
     */
    protected $nextOperation = [];

    /**
     * Create a blot.
     *
     * @param array $currentOperation The current operation.
     * @param array $previousOperation The next operation.
     * @param array $nextOperation The previous operation.
     */
    public function __construct(array $currentOperation, array $previousOperation = [], array $nextOperation = []) {
        $this->previousOperation = $previousOperation;
        $this->currentOperation = $currentOperation;
        $this->nextOperation = $nextOperation;
    }

    /**
     * Get the string of the attribute key in the insert that determines if the blot applies or not. This key should lead to a boolean value in the attributes array of the insert.
     *
     * @return string
     */
    abstract protected static function getAttributeLookupKey(): string;

    /**
     * Get the formats allowed that cannot be "nested" inside this one.
     *
     * If a format is set here, and is also present on the before/after/current operation, then this format will
     * always create start/end html tags.
     *
     * @example
     * <strong>Bold only<em>Bold and italic</em><strong> is allowed.
     * <em>Italic only<strong>italic and bold</strong></em> is not.
     *
     * @return array
     */
    abstract protected function getBlackListedNestedFormats(): array;

    /**
     * Get the name of the HTML tag for this blot.
     *
     * @return string
     */
    abstract protected function getTagName(): string;

    /**
     * @inheritDoc
     */
    public static function matches(array $operations): bool {
        $result = false;

        foreach($operations as $op) {
            $attributes = val("attributes", $op, []);
            if (array_key_exists(static::getAttributeLookupKey(), $attributes)) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Formats never actually render any content. They should only be providing starting/ending tags.
     */
    public function render(): string {
        return "";
    }

    /**
     * Get an attributes array for the blot's tag.
     */
    protected function getAttributes(): array {
        return [];
    }

    /**
     * @inheritDoc
     */
    private function shouldRenderOpeningTag(): bool {
        $selfMatchesPrevious = static::matches([$this->previousOperation]);
        $matchesBlackListedFormat = false;
        foreach(static::getBlackListedNestedFormats() as $blackListedFormat) {
            if ($blackListedFormat::matches([$this->previousOperation, $this->currentOperation])) {
                $matchesBlackListedFormat = true;
                break;
            }
        }

        return !$selfMatchesPrevious || $matchesBlackListedFormat;
    }

    /**
     * Render the opening tags for the current blot.
     */
    public function renderOpeningTag(): string {
        if (!$this->shouldRenderOpeningTag()) {
            return "";
        }

        $tagName = static::getTagName();
        $attributes =  $this->getAttributes();

        $result = "<".$tagName;
        if ($attributes) {
            foreach ($attributes as $attrKey => $attr) {
                $result .= " $attrKey=\"$attr\"";
            }
        }

        $result .= ">";
        return $result;
    }

    /**
     * @inheritDoc
     */
    private function shouldRenderClosingTag(): bool {
        $selfMatchesNext = static::matches([$this->nextOperation]);
        $matchesBlackListedFormat = false;
        foreach(static::getBlackListedNestedFormats() as $blackListedFormat) {
            if ($blackListedFormat::matches([$this->nextOperation, $this->currentOperation])) {
                $matchesBlackListedFormat = true;
                break;
            }
        }
        return !$selfMatchesNext || $matchesBlackListedFormat;
    }

    /**
     * Render the closing tags for the current blot.
     */
    public function renderClosingTag(): string {
        if (!$this->shouldRenderClosingTag()) {
            return "";
        }

        return "</".static::getTagName().">";
    }
}
