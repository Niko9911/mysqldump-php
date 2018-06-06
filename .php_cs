<?php
$header = '
NOTICE OF LICENSE
This source file is released under GPL V3 License
@copyright Copyright (c) Niko Granö & Contributors
@author Niko Granö <niko@ironlions.fi>
';

/** @noinspection PhpUndefinedNamespaceInspection */
$finder = PhpCsFixer\Finder::create()
    //->in('tests')
    ->in('src');

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP71Migration:risky' => true,
        'align_multiline_comment' => true,
        'array_syntax' => ['syntax' => 'short'],
        #'cast_spaces' => false,
        #'concat_space' => ['spacing' => 'one'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'mb_str_functions' => true,
        'native_function_invocation' => true,
        'no_php4_constructor' => true,
        'no_short_echo_tag' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'protected_to_private' => false,
        'phpdoc_align' => false,
        'strict_comparison' => true,
        'strict_param' => true,
        'header_comment' => array('header' => $header, 'commentType' => 'PHPDoc'),
        'method_argument_space' => ['ensure_fully_multiline' => true, 'keep_multiple_spaces_after_comma' => false],
    ])
    ->setIndent('    ')
    ->setLineEnding("\n")
    ->setFinder($finder);