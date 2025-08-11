<?php

declare(strict_types=1);

namespace CustomPHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects the use of unsafe functions.
 */
class UnsafeFunctionsRule implements Rule
{
    private const UNSAFE_FUNCTIONS = [
        // Filesystem functions
        'exec',
        'shell_exec',
        'passthru',
        'system',
        'proc_open',
        'popen',
        'pcntl_exec',
        // Eval functions
        'create_function',
        'assert',
    ];

    private const UNSAFE_CRYPTO_FUNCTIONS = [
        'md5',
        'sha1',
    ];

    public function getNodeType(): string
    {
        // Analyze both function calls and eval statements
        return Node::class;
    }

    /**
     * @param Node $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Handle eval() specifically
        if ($node instanceof Node\Expr\Eval_) {
            return [
                RuleErrorBuilder::message(
                    'The use of the unsafe function "eval" is prohibited for security reasons.',
                )->build(),
            ];
        }

        // Handle other unsafe functions
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $functionName = (string) $node->name;

            if (in_array($functionName, self::UNSAFE_FUNCTIONS, true)) {
                return [
                    RuleErrorBuilder::message(
                        sprintf(
                            'The use of the unsafe function "%s" is prohibited for security reasons.',
                            $functionName,
                        ),
                    )->build(),
                ];
            }

            // Check for unsafe cryptographic functions
            if (in_array($functionName, self::UNSAFE_CRYPTO_FUNCTIONS, true)) {
                return [
                    RuleErrorBuilder::message(
                        sprintf(
                            'The use of the insecure cryptographic function "%s" is discouraged. Use modern alternatives like "password_hash()" or "hash(\'sha256\', ...)" instead.',
                            $functionName,
                        ),
                    )->build(),
                ];
            }
        }

        return [];
    }
}
