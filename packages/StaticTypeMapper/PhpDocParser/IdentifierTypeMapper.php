<?php

declare(strict_types=1);

namespace Rector\StaticTypeMapper\PhpDocParser;

use PhpParser\Node;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ClassStringType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use Rector\Core\Enum\ObjectReference;
use Rector\NodeCollector\ScopeResolver\ParentClassScopeResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\StaticTypeMapper\Contract\PhpDocParser\PhpDocTypeMapperInterface;
use Rector\StaticTypeMapper\Mapper\ScalarStringToTypeMapper;
use Rector\StaticTypeMapper\ValueObject\Type\ParentStaticType;
use Rector\StaticTypeMapper\ValueObject\Type\SelfObjectType;
use Rector\TypeDeclaration\PHPStan\Type\ObjectTypeSpecifier;

final class IdentifierTypeMapper implements PhpDocTypeMapperInterface
{
    public function __construct(
        private ObjectTypeSpecifier $objectTypeSpecifier,
        private ScalarStringToTypeMapper $scalarStringToTypeMapper,
        private ParentClassScopeResolver $parentClassScopeResolver,
    ) {
    }

    /**
     * @return class-string<TypeNode>
     */
    public function getNodeType(): string
    {
        return IdentifierTypeNode::class;
    }

    /**
     * @param IdentifierTypeNode $typeNode
     */
    public function mapToPHPStanType(TypeNode $typeNode, Node $node, NameScope $nameScope): Type
    {
        $type = $this->scalarStringToTypeMapper->mapScalarStringToType($typeNode->name);
        if (! $type instanceof MixedType) {
            return $type;
        }

        if ($type->isExplicitMixed()) {
            return $type;
        }

        $loweredName = strtolower($typeNode->name);

        if ($loweredName === 'class-string') {
            return new ClassStringType();
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);

        if ($loweredName === ObjectReference::SELF()->getValue()) {
            return $this->mapSelf($scope);
        }

        if ($loweredName === ObjectReference::PARENT()->getValue()) {
            return $this->mapParent($scope);
        }

        if ($loweredName === ObjectReference::STATIC()->getValue()) {
            return $this->mapStatic($scope);
        }

        if ($loweredName === 'iterable') {
            return new IterableType(new MixedType(), new MixedType());
        }

        $objectType = new ObjectType($typeNode->name);

        $scope = $node->getAttribute(AttributeKey::SCOPE);
        return $this->objectTypeSpecifier->narrowToFullyQualifiedOrAliasedObjectType($node, $objectType, $scope);
    }

    private function mapSelf(Scope $scope): MixedType | SelfObjectType
    {
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            // self outside the class, e.g. in a function
            return new MixedType();
        }

        return new SelfObjectType($classReflection->getName(), null, $classReflection);
    }

    private function mapParent(Scope $scope): ParentStaticType | MixedType
    {
        $parentClassReflection = $this->parentClassScopeResolver->resolveParentClassReflection($scope);
        if (! $parentClassReflection instanceof ClassReflection) {
            return new MixedType();
        }

        return new ParentStaticType($parentClassReflection);
    }

    private function mapStatic(Scope $scope): MixedType | StaticType
    {
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return new MixedType();
        }

        return new StaticType($classReflection);
    }
}
