<?php

declare(strict_types=1);

namespace Rector\Generics\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\Generics\NodeType\GenericTypeSpecifier;
use Rector\Generics\Reflection\ClassGenericMethodResolver;
use Rector\Generics\Reflection\GenericClassReflectionAnalyzer;
use Rector\Generics\ValueObject\GenericChildParentClassReflections;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see https://github.com/phpstan/phpstan/issues/3167
 *
 * @see \Rector\Generics\Tests\Rector\Class_\GenericsPHPStormMethodAnnotationRector\GenericsPHPStormMethodAnnotationRectorTest
 */
final class GenericsPHPStormMethodAnnotationRector extends AbstractRector
{
    /**
     * @var ClassGenericMethodResolver
     */
    private $classGenericMethodResolver;

    /**
     * @var GenericTypeSpecifier
     */
    private $genericTypeSpecifier;

    /**
     * @var GenericClassReflectionAnalyzer
     */
    private $genericClassReflectionAnalyzer;

    public function __construct(
        ClassGenericMethodResolver $classGenericMethodResolver,
        GenericTypeSpecifier $genericTypeSpecifier,
        GenericClassReflectionAnalyzer $genericClassReflectionAnalyzer
    ) {
        $this->classGenericMethodResolver = $classGenericMethodResolver;
        $this->genericTypeSpecifier = $genericTypeSpecifier;
        $this->genericClassReflectionAnalyzer = $genericClassReflectionAnalyzer;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Complete PHPStorm @method annotations, to make it understand the PHPStan/Psalm generics',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
/**
 * @template TEntity as object
 */
abstract class AbstractRepository
{
    /**
     * @return TEntity
     */
    public function find($id)
    {
    }
}

/**
 * @template TEntity as SomeObject
 * @extends AbstractRepository<TEntity>
 */
final class AndroidDeviceRepository extends AbstractRepository
{
}
CODE_SAMPLE

                    ,
                    <<<'CODE_SAMPLE'
/**
 * @template TEntity as object
 */
abstract class AbstractRepository
{
    /**
     * @return TEntity
     */
    public function find($id)
    {
    }
}

/**
 * @template TEntity as SomeObject
 * @extends AbstractRepository<TEntity>
 * @method SomeObject find($id)
 */
final class AndroidDeviceRepository extends AbstractRepository
{
}
CODE_SAMPLE
                ),
            ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $genericChildParentClassReflections = $this->genericClassReflectionAnalyzer->resolveChildParent($node);
        if (! $genericChildParentClassReflections instanceof GenericChildParentClassReflections) {
            return null;
        }

        // resolve generic method from parent
        $methodTagValueNodes = $this->classGenericMethodResolver->resolveFromClass(
            $genericChildParentClassReflections->getParentClassReflection()
        );

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        $methodTagValueNodes = $this->filterOutExistingMethodTagValuesNodes($methodTagValueNodes, $phpDocInfo);

        $this->genericTypeSpecifier->replaceGenericTypesWithSpecificTypes(
            $methodTagValueNodes,
            $node,
            $genericChildParentClassReflections->getChildClassReflection()
        );

        foreach ($methodTagValueNodes as $methodTagValueNode) {
            $phpDocInfo->addTagValueNode($methodTagValueNode);
        }

        return $node;
    }

    /**
     * @param MethodTagValueNode[] $methodTagValueNodes
     * @return MethodTagValueNode[]
     */
    private function filterOutExistingMethodTagValuesNodes(
        array $methodTagValueNodes,
        PhpDocInfo $phpDocInfo
    ): array {
        $methodTagNames = $phpDocInfo->getMethodTagNames();
        if ($methodTagNames === []) {
            return $methodTagValueNodes;
        }

        $filteredMethodTagValueNodes = [];
        foreach ($methodTagValueNodes as $methodTagValueNode) {
            if (in_array($methodTagValueNode->methodName, $methodTagNames, true)) {
                continue;
            }

            $filteredMethodTagValueNodes[] = $methodTagValueNode;
        }

        return $filteredMethodTagValueNodes;
    }
}