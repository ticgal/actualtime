<?php

declare(strict_types=1);

namespace CustomPHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects the use of functions that may expose sensitive information.
 */
class SensitiveInfoExposureRule implements Rule
{
    private const SENSITIVE_FUNCTIONS = [
        'phpinfo',
        'var_dump',
        'print_r',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class; // Analyzes function calls.
    }

    /**
     * @param FuncCall $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name instanceof Node\Name) {
            $functionName = (string) $node->name;
            if (in_array($functionName, self::SENSITIVE_FUNCTIONS, true)) {
                // Special handling for print_r
                if ($functionName === 'print_r') {
                    // Check if the second argument is set and is `true`
                    if (isset($node->args[1])) {
                        $secondArg = $node->args[1]->value;

                        // Allow only if the second argument is a literal `true`
                        if ($secondArg->name->toLowerString() === 'true') {
                            return []; // No error if print_r($var, true)
                        }
                    }

                    // If no second argument or it's not `true`, report an error
                    return [
                        RuleErrorBuilder::message(
                            'The use of "print_r" without the second argument as `true` may expose sensitive information and should be avoided in production environments.',
                        )
                        ->tip('Use "print_r($var, true)" to avoid direct output or consider removing this call.')
                        ->build(),
                    ];
                }

                return [
                    RuleErrorBuilder::message(
                        sprintf(
                            'The use of the function "%s" may expose sensitive information and should be avoided in production environments.',
                            $functionName,
                        ),
                    )->build(),
                ];
            }
        }

        return [];
    }
}
