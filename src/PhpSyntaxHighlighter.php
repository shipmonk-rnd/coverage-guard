<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use function extension_loaded;
use function getenv;
use function is_array;
use function token_get_all;
use const T_ABSTRACT;
use const T_AND_EQUAL;
use const T_ARRAY;
use const T_AS;
use const T_BREAK;
use const T_CALLABLE;
use const T_CASE;
use const T_CATCH;
use const T_CLASS;
use const T_CLASS_C;
use const T_CLONE;
use const T_COMMENT;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CONTINUE;
use const T_DECLARE;
use const T_DEFAULT;
use const T_DIR;
use const T_DO;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_ECHO;
use const T_ELSE;
use const T_ELSEIF;
use const T_EMPTY;
use const T_ENDDECLARE;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_ENDWHILE;
use const T_ENUM;
use const T_EVAL;
use const T_EXIT;
use const T_EXTENDS;
use const T_FILE;
use const T_FINAL;
use const T_FINALLY;
use const T_FN;
use const T_FOR;
use const T_FOREACH;
use const T_FUNC_C;
use const T_FUNCTION;
use const T_GLOBAL;
use const T_GOTO;
use const T_HALT_COMPILER;
use const T_IF;
use const T_IMPLEMENTS;
use const T_INCLUDE;
use const T_INCLUDE_ONCE;
use const T_INSTANCEOF;
use const T_INSTEADOF;
use const T_INTERFACE;
use const T_ISSET;
use const T_LINE;
use const T_LIST;
use const T_LNUMBER;
use const T_MATCH;
use const T_METHOD_C;
use const T_NAMESPACE;
use const T_NEW;
use const T_NS_C;
use const T_OBJECT_OPERATOR;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_READONLY;
use const T_REQUIRE;
use const T_REQUIRE_ONCE;
use const T_RETURN;
use const T_STATIC;
use const T_SWITCH;
use const T_THROW;
use const T_TRAIT;
use const T_TRAIT_C;
use const T_TRY;
use const T_UNSET;
use const T_USE;
use const T_VAR;
use const T_VARIABLE;
use const T_WHILE;
use const T_YIELD;
use const T_YIELD_FROM;

final class PhpSyntaxHighlighter
{

    private const string COLOR_RESET = "\033[0m";
    private const string COLOR_KEYWORD = "\033[35m"; // Magenta
    private const string COLOR_STRING = "\033[32m"; // Green
    private const string COLOR_COMMENT = "\033[90m"; // Dark gray
    private const string COLOR_VARIABLE = "\033[36m"; // Cyan
    private const string COLOR_NUMBER = "\033[33m"; // Yellow

    public function highlight(string $code): string
    {
        if (!extension_loaded('tokenizer')) {
            return $code;
        }

        if (getenv('NO_COLOR') !== false) {
            return $code;
        }

        $tokens = token_get_all("<?php\n{$code}");
        $output = '';
        $skipFirst = true;

        foreach ($tokens as $token) {
            // Skip the injected <?php tag
            if ($skipFirst) {
                $skipFirst = false;
                continue;
            }

            if (is_array($token)) {
                [$tokenType, $tokenValue] = $token;

                $output .= match (true) {
                    $tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT => self::COLOR_COMMENT . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_VARIABLE => self::COLOR_VARIABLE . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_CONSTANT_ENCAPSED_STRING => self::COLOR_STRING . $tokenValue . self::COLOR_RESET,
                    $tokenType === T_LNUMBER => self::COLOR_NUMBER . $tokenValue . self::COLOR_RESET,
                    $this->isKeyword($tokenType) => self::COLOR_KEYWORD . $tokenValue . self::COLOR_RESET,
                    default => $tokenValue,
                };
            } else {
                $output .= $token;
            }
        }

        return $output;
    }

    private function isKeyword(int $tokenType): bool
    {
        return match ($tokenType) {
            T_ABSTRACT, T_AND_EQUAL, T_ARRAY, T_AS, T_BREAK, T_CALLABLE, T_CASE, T_CATCH,
            T_CLASS, T_CLASS_C, T_CLONE, T_CONST, T_CONTINUE, T_DECLARE, T_DEFAULT,
            T_DIR, T_DO, T_ECHO, T_ELSE, T_ELSEIF, T_EMPTY, T_ENDDECLARE, T_ENDFOR,
            T_ENDFOREACH, T_ENDIF, T_ENDSWITCH, T_ENDWHILE, T_EVAL, T_EXIT, T_EXTENDS,
            T_FILE, T_FINAL, T_FINALLY, T_FN, T_FOR, T_FOREACH, T_FUNCTION, T_FUNC_C,
            T_GLOBAL, T_GOTO, T_HALT_COMPILER, T_IF, T_IMPLEMENTS, T_INCLUDE,
            T_INCLUDE_ONCE, T_INSTANCEOF, T_INSTEADOF, T_INTERFACE, T_ISSET, T_LINE,
            T_LIST, T_MATCH, T_METHOD_C, T_NAMESPACE, T_NEW, T_NS_C, T_PRIVATE,
            T_PROTECTED, T_PUBLIC, T_READONLY, T_REQUIRE, T_REQUIRE_ONCE, T_RETURN,
            T_STATIC, T_SWITCH, T_THROW, T_TRAIT, T_TRAIT_C, T_TRY, T_UNSET, T_USE,
            T_VAR, T_WHILE, T_YIELD, T_YIELD_FROM, T_ENUM,
            T_DOUBLE_COLON, T_OBJECT_OPERATOR => true,
            default => false,
        };
    }

}
