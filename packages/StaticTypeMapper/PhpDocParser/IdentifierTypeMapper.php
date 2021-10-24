<?php

declare(strict_types=1);

namespace Rector\StaticTypeMapper\PhpDocParser;

use PhpParser\Node;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Reflection\ClassReflection;
<<<<<<< HEAD
=======
use PHPStan\Reflection\ReflectionProvider;
>>>>>>> StaticType requires ClassReflection on constructor
use PHPStan\Type\ClassStringType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StaticType;
use PHPStan\Type\Type;
use Rector\Core\Enum\ObjectReference;
use Rector\Core\Exception\ShouldNotHappenException;
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
<<<<<<< HEAD
=======
        private ReflectionProvider $reflectionProvider
>>>>>>> NativeFunctionReflection has new parameter
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
            return $this->mapSelf($node);
        }

        if ($loweredName === ObjectReference::PARENT()->getValue()) {
            return $this->mapParent($scope);
        }

        if ($loweredName === ObjectReference::STATIC()->getValue()) {
            return $this->mapStatic($node, $scope);
        }

        if ($loweredName === 'iterable') {
            return new IterableType(new MixedType(), new MixedType());
        }

        $objectType = new ObjectType($typeNode->name);

        $scope = $node->getAttribute(AttributeKey::SCOPE);
        return $this->objectTypeSpecifier->narrowToFullyQualifiedOrAliasedObjectType($node, $objectType, $scope);
    }

    private function mapSelf(Node $node): MixedType | SelfObjectType
    {
        /** @var string|null $className */
        $className = $node->getAttribute(AttributeKey::CLASS_NAME);
        if ($className === null) {
            // self outside the class, e.g. in a function
            return new MixedType();
        }

        return new SelfObjectType($className);
    }

    private function mapParent(Scope $scope): ParentStaticType | MixedType
    {
<<<<<<< HEAD
        $parentClassReflection = $this->parentClassScopeResolver->resolveParentClassReflection($scope);
        if (! $parentClassReflection instanceof ClassReflection) {
            return new MixedType();
        }

        return new ParentStaticType($parentClassReflection);
=======
<<<<<<< HEAD
        $parentClassName = $this->parentClassScopeResolver->resolveParentClassName($node);
        if ($parentClassName === null) {
            return new MixedType();
        }

        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            throw new ShouldNotHappenException();
=======
        $parentClassReflection = $this->parentClassScopeResolver->resolveParentClassReflection($node);
        if ($parentClassReflection instanceof ClassReflection) {
            return new ParentStaticType($parentClassReflection);
>>>>>>> StaticType requires ClassReflection on constructor
        }

        return new ParentStaticType($classReflection);
>>>>>>> NativeFunctionReflection has new parameter
    }

    private function mapStatic(Node $node, Scope $scope): MixedType | StaticType
    {
        /** @var string|null $className */
        $className = $node->getAttribute(AttributeKey::CLASS_NAME);
        if ($className === null) {
            return new MixedType();
        }

<<<<<<< HEAD
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            throw new ShouldNotHappenException();
        }

=======
        if (! $this->reflectionProvider->hasClass($className)) {
            return new MixedType();
        }

        $classReflection = $this->reflectionProvider->getClass($className);
>>>>>>> StaticType requires ClassReflection on constructor
        return new StaticType($classReflection);
    }
}
