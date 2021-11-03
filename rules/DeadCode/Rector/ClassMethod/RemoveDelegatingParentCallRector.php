<?php

declare(strict_types=1);

namespace Rector\DeadCode\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Rector\Core\Rector\AbstractRector;
use Rector\DeadCode\Comparator\CurrentAndParentClassMethodComparator;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\DeadCode\Rector\ClassMethod\RemoveDelegatingParentCallRector\RemoveDelegatingParentCallRectorTest
 */
final class RemoveDelegatingParentCallRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private const ALLOWED_ANNOTATIONS = [
        'Route',
        'required',
    ];

    /**
     * @var string[]
     */
    private const ALLOWED_ATTRIBUTES = [
        'Symfony\Component\Routing\Annotation\Route',
        'Symfony\Contracts\Service\Attribute\Required',
    ];

    public function __construct(
        private CurrentAndParentClassMethodComparator $currentAndParentClassMethodComparator
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Removed dead parent call, that does not change anything',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeClass
{
    public function prettyPrint(array $stmts): string
    {
        return parent::prettyPrint($stmts);
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class SomeClass
{
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $classLike = $node->getAttribute(AttributeKey::CLASS_NODE);
        if ($this->shouldSkipClass($classLike)) {
            return null;
        }

        $onlyStmt = $this->matchClassMethodOnlyStmt($node);
        if ($onlyStmt === null) {
            return null;
        }

        // are both return?
        if ($this->isMethodReturnType($node, 'void') && ! $onlyStmt instanceof Return_) {
            return null;
        }

        $staticCall = $this->matchStaticCall($onlyStmt);
        if (! $staticCall instanceof StaticCall) {
            return null;
        }

        if (! $this->currentAndParentClassMethodComparator->isParentCallMatching($node, $staticCall)) {
            return null;
        }

        if ($this->shouldSkipWithAnnotationsOrAttributes($node)) {
            return null;
        }

        // the method is just delegation, nothing extra
        $this->removeNode($node);

        return null;
    }

    private function shouldSkipClass(?ClassLike $classLike): bool
    {
        if (! $classLike instanceof Class_) {
            return true;
        }

        return $classLike->extends === null;
    }

    private function isMethodReturnType(ClassMethod $classMethod, string $type): bool
    {
        if ($classMethod->returnType === null) {
            return false;
        }

        return $this->isName($classMethod->returnType, $type);
    }

    private function matchStaticCall(Expr|Stmt $node): ?StaticCall
    {
        // must be static call
        if ($node instanceof Return_) {
            if ($node->expr instanceof StaticCall) {
                return $node->expr;
            }

            return null;
        }

        if ($node instanceof StaticCall) {
            return $node;
        }

        return null;
    }

    private function shouldSkipWithAnnotationsOrAttributes(ClassMethod $classMethod): bool
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);
        if ($phpDocInfo->hasByNames(self::ALLOWED_ANNOTATIONS)) {
            return true;
        }

        $attrGroups = $classMethod->attrGroups;
        if ($attrGroups === []) {
            return false;
        }

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr instanceof Node\Attribute) {
                    $name = (string) $attr->name;
                    if (in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function matchClassMethodOnlyStmt(ClassMethod $classMethod): null | Stmt | Expr
    {
        if ($classMethod->stmts === null) {
            return null;
        }

        if (count((array) $classMethod->stmts) !== 1) {
            return null;
        }

        // recount empty notes
        $stmtsValues = array_values($classMethod->stmts);

        return $this->unwrapExpression($stmtsValues[0]);
    }
}
