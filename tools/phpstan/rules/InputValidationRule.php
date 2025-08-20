<?php

declare(strict_types=1);

namespace CustomPHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects the use of superglobals without validation.
 */
class InputValidationRule implements Rule
{
    private const SUPERGLOBALS = [
        '_GET',
        '_POST',
        '_REQUEST',
        '_COOKIE',
    ];

    public function getNodeType(): string
    {
        return Variable::class; // Analyzes variable usage.
    }

    /**
     * @param Variable $node
     * @param Scope $scope
     * @return array
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Get the file path being analyzed
        $filePath = $scope->getFile();

        // Skip files in "ajax" or "front" directories
        if (strpos($filePath, '/ajax/') !== false || strpos($filePath, '/front/') !== false) {
            return []; // Do not apply the rule to these files
        }

        // If file is apirest.class.php, skip the rule
        if (strpos($filePath, '/apirest.class.php') !== false) {
            return []; // Do not apply the rule to this file
        }

        // Get the function name
        $functionName = $scope->getFunctionName();

        // Allowed functions
        if (in_array($functionName, ['prepareInputForAdd', 'prepareInputForUpdate'], true)) {
            return []; // Do not apply the rule to these functions
        }

        if (is_string($node->name) && in_array($node->name, self::SUPERGLOBALS, true)) {
            // Check if the superglobal is being accessed with a specific key
            // If not, allow assignment Ex. $get = $_GET;
            if ($node->getAttribute('key') === null) {
                return [];
            }

            return [
                RuleErrorBuilder::message(
                    sprintf(
                        'The use of the superglobal "%s" without validation is discouraged out of ajax and front files.',
                        print_r($node->name, true),
                    ),
                )->build(),
            ];
        }

        return [];
    }
}
