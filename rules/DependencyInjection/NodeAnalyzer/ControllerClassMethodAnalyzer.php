<?php

declare(strict_types=1);

namespace Rector\DependencyInjection\NodeAnalyzer;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class ControllerClassMethodAnalyzer
{
    public function isInControllerActionMethod(Variable $variable, Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return false;
        }

        $className = $classReflection->getName();
        if (! \str_ends_with($className, 'Controller')) {
            return false;
        }

        $classMethod = $variable->getAttribute(AttributeKey::METHOD_NODE);
        if (! $classMethod instanceof ClassMethod) {
            return false;
        }

        // is probably in controller action
        return $classMethod->isPublic();
    }
}
