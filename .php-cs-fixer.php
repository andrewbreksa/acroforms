<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PER-CS' => true,
        '@PHP80Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'blank_line_after_namespace' => true,
        'elseif' => true,
        'encoding' => true,
        'no_empty_statement' => true,
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align',
                '=' => 'align',
            ],
        ],
        'random_api_migration' => [
            'replacements' => [
                'mt_rand' => 'random_int',
                'rand' => 'random_int',
                'getrandmax' => 'mt_getrandmax',
                'srand' => 'mt_srand',
            ],
        ],
        'short_scalar_cast' => true,
        'single_quote' => true,
        'explicit_string_variable' => true,
        'combine_consecutive_unsets' => true,
        'combine_consecutive_issets' => true,
        'fully_qualified_strict_types' => true,
        'simplified_if_return' => true,
        'return_assignment' => false,
        'align_multiline_comment' => true,
        'no_superfluous_elseif' => true,
        'simple_to_complex_string_variable' => true,
        'no_break_comment' => false,
        'heredoc_indentation' => false,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);