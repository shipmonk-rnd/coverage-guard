<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Ast;

use LogicException;
use PhpParser\Error as ParseError;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser as PhpParser;
use ShipMonk\CoverageGuard\CodeBlockAnalyser;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function implode;
use const PHP_EOL;

final class FileTraverser
{

    public function __construct(
        private readonly PhpParser $phpParser,
    )
    {
    }

    /**
     * @param array<string> $fileLines
     *
     * @throws ErrorException
     */
    public function traverse(
        string $file,
        array $fileLines,
        CodeBlockAnalyser $analyser,
    ): void
    {
        $nameResolver = new NameResolver();

        $nameResolvingTraverser = new NodeTraverser();
        $nameResolvingTraverser->addVisitor($nameResolver);

        $analyserTraverser = new NodeTraverser();
        $analyserTraverser->addVisitor($analyser);

        try {
            /** @throws ParseError */
            $ast = $this->phpParser->parse(implode(PHP_EOL, $fileLines));
        } catch (ParseError $e) {
            throw new ErrorException("Failed to parse PHP code in file {$file}: {$e->getMessage()}", $e);
        }

        if ($ast === null) {
            throw new LogicException("Failed to parse PHP code in file {$file}. Should never happen as Throwing error handler is used.");
        }

        $analyserTraverser->traverse($nameResolvingTraverser->traverse($ast));
    }

}
