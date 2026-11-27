<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\StringLiteralKind;
use Mago\Sdk\Analyzer\Type\StringType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_values;
use function count;
use function explode;
use function implode;
use function is_string;
use function stripos;
use function strtolower;

/**
 * Opt-in check (`MAGO_MOCKERY_STRICT_METHODS=1`) that validates literal and higher-order
 * method names passed to Mockery expectation and verification calls against the mocked target class.
 *
 * @internal
 */
final class StrictMockedMethodHook implements MethodCallAnalysisHook
{
    public const ISSUE_CODE = 'unknown-mocked-method';

    public const MODE_VARIADIC = 'variadic';
    public const MODE_SINGLE = 'single';
    public const MODE_HIGHER_ORDER = 'higher-order';

    private const MOCK_CLASSES = [
        'Mockery\\Mock',
        'Mockery\\MockInterface',
        'Mockery\\LegacyMockInterface',
    ];

    public function __construct(
        private readonly string $mode = self::MODE_VARIADIC,
    ) {}

    public function getTargets(): array
    {
        if ($this->mode === self::MODE_HIGHER_ORDER) {
            return [
                MethodTarget::allMethods('Mockery\\HigherOrderMessage'),
                MethodTarget::allMethods('Mockery\\ExpectsHigherOrderMessage'),
            ];
        }

        if ($this->mode === self::MODE_VARIADIC) {
            $targets = [];
            foreach (self::MOCK_CLASSES as $class) {
                $targets[] = MethodTarget::exact($class, 'shouldReceive');
                $targets[] = MethodTarget::exact($class, 'shouldNotReceive');
            }
            $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldReceive');
            $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldNotReceive');

            return $targets;
        }

        $targets = [];
        foreach (self::MOCK_CLASSES as $class) {
            $targets[] = MethodTarget::exact($class, 'allows');
            $targets[] = MethodTarget::exact($class, 'expects');
            $targets[] = MethodTarget::exact($class, 'shouldHaveReceived');
            $targets[] = MethodTarget::exact($class, 'shouldNotHaveReceived');
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        if ($this->mode === self::MODE_HIGHER_ORDER) {
            return [
                FileAnalysisRequirement::SourceText,
                FileAnalysisRequirement::TargetSubtree,
                FileAnalysisRequirement::ReceiverType,
            ];
        }

        return [
            FileAnalysisRequirement::SourceText,
            FileAnalysisRequirement::ReceiverType,
            FileAnalysisRequirement::ArgumentTypes,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($this->mode === self::MODE_HIGHER_ORDER) {
            $called = StrictAllowanceResolver::calledMethodName($context->source, $context->node);
            if ($called === null || str_starts_with($called, '__')) {
                return;
            }
            $methodNames = [$called];
            $mockType = HigherOrderMessageProvider::extractMockType($context->receiverType);
            $targetClasses = MockTypeFactory::extractTargetClasses($mockType);
        } else {
            $methodNames = $this->extractLiteralMethodNames($context->argumentTypes);
            $targetClasses = MockTypeFactory::extractTargetClasses($context->receiverType);
        }

        if ($methodNames === [] || $targetClasses === []) {
            return;
        }

        $validMetadata = MockTypeFactory::resolveStrictTargetMetadata($targetClasses, $context->codebase);
        if ($validMetadata === null) {
            return;
        }

        $targetLabel = implode('&', $targetClasses);
        foreach ($methodNames as $methodName) {
            if (MockTypeFactory::methodExistsOnTargets($targetClasses, $validMetadata, $methodName, $context->codebase)) {
                continue;
            }

            if ($this->isAllowedViaMockingMethod($context, $methodName)) {
                continue;
            }

            $context->report(
                Level::Error,
                self::ISSUE_CODE,
                Issue::new(
                    "Method `{$methodName}` does not exist on mocked type `{$targetLabel}`.",
                    $context->node->span,
                )->withHelp(
                    'Use an existing method on the mocked class, or unset MAGO_MOCKERY_STRICT_METHODS when mocking dynamic methods.',
                ),
            );
        }
    }

    /**
     * @param list<Type|null> $argumentTypes
     * @return list<string>
     */
    private function extractLiteralMethodNames(array $argumentTypes): array
    {
        if ($argumentTypes === []) {
            return [];
        }

        $toInspect = $this->mode === self::MODE_VARIADIC ? $argumentTypes : [$argumentTypes[0]];
        /** @var array<string, string> $namesByLower */
        $namesByLower = [];
        foreach ($toInspect as $type) {
            if ($type === null) {
                continue;
            }

            $literals = self::extractFiniteLiteralStrings($type);
            if ($literals !== null) {
                foreach ($literals as $literal) {
                    $first = explode('->', $literal, 2)[0];
                    if ($first !== '') {
                        $namesByLower[strtolower($first)] ??= $first;
                    }
                }

                continue;
            }

            if (count($type->atomicTypes) !== 1) {
                continue;
            }

            $atomic = $type->atomicTypes[0];
            if ($atomic instanceof KeyedArrayType && $atomic->knownItems !== null) {
                foreach ($atomic->knownItems as $item) {
                    if ($item->key->kind === ArrayKeyKind::String && is_string($item->key->value)) {
                        $first = explode('->', $item->key->value, 2)[0];
                        if ($first !== '') {
                            $namesByLower[strtolower($first)] ??= $first;
                        }
                    }
                }
            }
        }

        return array_values($namesByLower);
    }

    /**
     * Extracts every literal-string member of a finite union of literal strings,
     * returning null if any member is non-literal (e.g. `string`, `mixed`, `null`).
     *
     * @return ?list<string>
     */
    private static function extractFiniteLiteralStrings(Type $type): ?array
    {
        $literals = [];
        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof ScalarType) {
                return null;
            }

            if ($atomic->refinement instanceof StringType) {
                if ($atomic->refinement->literalKind !== StringLiteralKind::Value || $atomic->refinement->literalValue === null) {
                    return null;
                }
                $literals[] = $atomic->refinement->literalValue;
                continue;
            }

            if (
                $atomic->refinement instanceof ClassLikeStringType
                && $atomic->refinement->variant === ClassLikeStringVariant::Literal
                && $atomic->refinement->literal !== null
            ) {
                $literals[] = $atomic->refinement->literal;
                continue;
            }

            return null;
        }

        return $literals;
    }

    private function isAllowedViaMockingMethod(NodeAnalysisContext $context, string $methodName): bool
    {
        if (stripos($context->source->contents, 'shouldAllowMockingMethod') === false) {
            return false;
        }

        $source = $context->analysis->getSourceFile();
        $call = $this->findMethodCallBySpan($source, $context->node->span);
        if ($call === null) {
            return false;
        }

        return StrictAllowanceResolver::isAllowed($source, $call, strtolower($methodName), $context->codebase);
    }

    private function findMethodCallBySpan(SourceFile $source, Span $targetSpan): ?Node
    {
        $current = $source->getNode(0);
        if ($current->span->start <= $targetSpan->start && $current->span->end >= $targetSpan->end) {
            while (true) {
                if (
                    $current->kind === NodeKind::MethodCall
                    && $current->span->start === $targetSpan->start
                    && $current->span->end === $targetSpan->end
                ) {
                    return $current;
                }

                $next = null;
                foreach ($source->getChildren($current) as $child) {
                    if ($child->span->start <= $targetSpan->start && $child->span->end >= $targetSpan->end) {
                        $next = $child;
                        break;
                    }
                }
                if ($next === null) {
                    break;
                }
                $current = $next;
            }
        }

        foreach ($source->getNodes(NodeKind::MethodCall) as $methodCall) {
            if ($methodCall->span->start === $targetSpan->start && $methodCall->span->end === $targetSpan->end) {
                return $methodCall;
            }
        }

        return null;
    }
}
