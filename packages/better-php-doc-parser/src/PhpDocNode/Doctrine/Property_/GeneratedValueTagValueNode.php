<?php

declare(strict_types=1);

namespace Rector\BetterPhpDocParser\PhpDocNode\Doctrine\Property_;

use Doctrine\ORM\Mapping\GeneratedValue;
use Rector\BetterPhpDocParser\PhpDocNode\Doctrine\AbstractDoctrineTagValueNode;
use Rector\PhpAttribute\Contract\PhpAttributableTagNodeInterface;
use Rector\PhpAttribute\PhpDocNode\PhpAttributePhpDocNodePrintTrait;

/**
 * @see \Rector\BetterPhpDocParser\Tests\PhpDocParser\TagValueNodeReprint\TagValueNodeReprintTest
 */
final class GeneratedValueTagValueNode extends AbstractDoctrineTagValueNode implements PhpAttributableTagNodeInterface
{
    use PhpAttributePhpDocNodePrintTrait;

    /**
     * @var mixed[]
     */
    private $items = [];

    public function __construct(array $items, ?string $annotationContent = null)
    {
        $this->items = $items;
        $this->resolveOriginalContentSpacingAndOrder($annotationContent, 'strategy');
    }

    public function __toString(): string
    {
        $items = $this->completeItemsQuotes($this->items);
        $items = $this->makeKeysExplicit($items);

        return $this->printContentItems($items);
    }

    public static function createFromAnnotationAndAnnotationContent(
        GeneratedValue $generatedValue,
        string $annotationContent
    ): self {
        $items = get_object_vars($generatedValue);

        return new self($items, $annotationContent);
    }

    public function getShortName(): string
    {
        return '@ORM\GeneratedValue';
    }

    public function toAttributeString(): string
    {
        // @todo add strategy
        return $this->printAttributeContent();
    }
}
