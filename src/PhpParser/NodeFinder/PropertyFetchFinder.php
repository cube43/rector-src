<?php

declare(strict_types=1);

namespace Rector\Core\PhpParser\NodeFinder;

use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Core\Enum\ObjectReference;
use Rector\Core\NodeAnalyzer\ClassAnalyzer;
use Rector\Core\PhpParser\AstResolver;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class PropertyFetchFinder
{
    /**
     * @var string
     */
    private const THIS = 'this';

    public function __construct(
        private BetterNodeFinder $betterNodeFinder,
        private NodeNameResolver $nodeNameResolver,
        private ReflectionProvider $reflectionProvider,
        private AstResolver $astResolver,
        private ClassAnalyzer $classAnalyzer
    ) {
    }

    /**
     * @return PropertyFetch[]|StaticPropertyFetch[]
     */
    public function findPrivatePropertyFetches(Property | Param $propertyOrPromotedParam): array
    {
        $classLike = $propertyOrPromotedParam->getAttribute(AttributeKey::CLASS_NODE);
        if (! $classLike instanceof Class_) {
            return [];
        }

        $propertyName = $this->resolvePropertyName($propertyOrPromotedParam);
        if ($propertyName === null) {
            return [];
        }

        $className = (string) $this->nodeNameResolver->getName($classLike);
        if (! $this->reflectionProvider->hasClass($className)) {
            return $this->findPropertyFetchesInClassLike($classLike->stmts, $propertyName);
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        $nodes = [$classLike];
        $nodes = array_merge($nodes, $this->astResolver->parseClassReflectionTraits($classReflection));

        return $this->findPropertyFetchesInNonAnonymousClassLike($nodes, $propertyName);
    }

    /**
     * @return PropertyFetch[]|StaticPropertyFetch[]
     */
    public function findLocalPropertyFetchesByName(Class_ $class, string $paramName): array
    {
        /** @var PropertyFetch[]|StaticPropertyFetch[] $propertyFetches */
        $propertyFetches = $this->betterNodeFinder->findInstancesOf(
            $class,
            [PropertyFetch::class, StaticPropertyFetch::class]
        );

        $foundPropertyFetches = [];

        foreach ($propertyFetches as $propertyFetch) {
            if ($propertyFetch instanceof PropertyFetch && ! $this->nodeNameResolver->isName(
                $propertyFetch->var,
                self::THIS
            )) {
                continue;
            }

            if ($propertyFetch instanceof StaticPropertyFetch && ! $this->nodeNameResolver->isName(
                $propertyFetch->class,
                ObjectReference::SELF()->getValue()
            )) {
                continue;
            }

            if (! $this->nodeNameResolver->isName($propertyFetch->name, $paramName)) {
                continue;
            }

            $foundPropertyFetches[] = $propertyFetch;
        }

        return $foundPropertyFetches;
    }

    /**
     * @param Stmt[] $stmts
     * @return PropertyFetch[]|StaticPropertyFetch[]
     */
    private function findPropertyFetchesInNonAnonymousClassLike(array $stmts, string $propertyName): array
    {
        /** @var PropertyFetch[]|StaticPropertyFetch[] $propertyFetches */
        $propertyFetches = $this->findPropertyFetchesInClassLike($stmts, $propertyName);

        foreach ($propertyFetches as $key => $propertyFetch) {
            $currentClassLike = $propertyFetch->getAttribute(AttributeKey::CLASS_NODE);

            if (! $currentClassLike instanceof ClassLike) {
                continue;
            }

            if ($this->classAnalyzer->isAnonymousClass($currentClassLike)) {
                unset($propertyFetches[$key]);
            }
        }

        return $propertyFetches;
    }

    /**
     * @param Stmt[] $stmts
     * @return PropertyFetch[]|StaticPropertyFetch[]
     */
    private function findPropertyFetchesInClassLike(array $stmts, string $propertyName): array
    {
        /** @var PropertyFetch[] $propertyFetches */
        $propertyFetches = $this->betterNodeFinder->findInstanceOf($stmts, PropertyFetch::class);

        /** @var PropertyFetch[] $matchingPropertyFetches */
        $matchingPropertyFetches = array_filter($propertyFetches, function (PropertyFetch $propertyFetch) use (
            $propertyName
        ): bool {
            if (! $this->nodeNameResolver->isName($propertyFetch->var, self::THIS)) {
                return false;
            }

            return $this->nodeNameResolver->isName($propertyFetch->name, $propertyName);
        });

        /** @var StaticPropertyFetch[] $staticPropertyFetches */
        $staticPropertyFetches = $this->betterNodeFinder->findInstanceOf($stmts, StaticPropertyFetch::class);

        /** @var StaticPropertyFetch[] $matchingStaticPropertyFetches */
        $matchingStaticPropertyFetches = array_filter(
            $staticPropertyFetches,
            fn (StaticPropertyFetch $staticPropertyFetch): bool => $this->isLocalStaticPropertyByFetchName(
                $staticPropertyFetch,
                $propertyName
            )
        );

        return array_merge($matchingPropertyFetches, $matchingStaticPropertyFetches);
    }

    private function resolvePropertyName(Property | Param $propertyOrPromotedParam): ?string
    {
        if ($propertyOrPromotedParam instanceof Property) {
            return $this->nodeNameResolver->getName($propertyOrPromotedParam->props[0]);
        }

        return $this->nodeNameResolver->getName($propertyOrPromotedParam->var);
    }

    private function isLocalStaticPropertyByFetchName(
        StaticPropertyFetch $staticPropertyFetch,
        string $propertyName
    ): bool {
        $class = $this->nodeNameResolver->getName($staticPropertyFetch->class);
        if (! in_array(
            $class,
            [ObjectReference::SELF()->getValue(), ObjectReference::STATIC()->getValue(), self::THIS],
            true
        )) {
            return false;
        }

        return $this->nodeNameResolver->isName($staticPropertyFetch->name, $propertyName);
    }
}
