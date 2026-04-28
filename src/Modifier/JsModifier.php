<?php

declare(strict_types=1);

namespace Demeve\Template\Modifier;

use Demeve\Template\ModifierInterface;

/**
 * Minifies each JS section, then prepends a block comment with the component name.
 * Minification runs first so it strips existing comments without removing the
 * component identifier that is added afterwards.
 *
 * Output format:
 *   /* ComponentName *\/
 *   minified js here
 */
class JsModifier implements ModifierInterface
{
    public function process(array $sections): string
    {
        $parts = [];
        foreach ($sections as $component => $content) {
            $parts[] = "/* {$component} */\n" . $this->minify($content);
        }
        return implode("\n", $parts);
    }

    private function minify(string $js, bool $safe = true): string
    {
        $js = str_replace(["\r\n", "\r"], "\n", $js);
        $len = strlen($js);
        if ($len === 0) {
            return '';
        }

        $outArr = [];
        $outCount = 0;
        $pos = 0;

        $inString = false;
        $stringChar = '';
        $inRegex = false;
        $inBlockComment = false;
        $inLineComment = false;

        $templateStack = [];
        $lastOutChar = "\n";
        $lastMeaningfulChar = "\n";

        $regexStartersCh = "=([:,!&|?;{\n<>|+-*~^%";

        while ($pos < $len) {
            $c = $js[$pos];
            $next = ($pos + 1 < $len) ? $js[$pos + 1] : null;

            if ($inBlockComment) {
                if ($c === '*' && $next === '/') {
                    $inBlockComment = false;
                    $pos += 2;
                } else {
                    $pos++;
                }
                continue;
            }

            if ($inLineComment) {
                if ($c === "\n") {
                    $inLineComment = false;
                } else {
                    $pos++;
                    continue;
                }
            }

            if ($inString) {
                $outArr[] = $c;
                $outCount++;
                if ($c === '\\' && $next !== null) {
                    $outArr[] = $next;
                    $outCount++;
                    $pos += 2;
                    continue;
                }
                if ($c === $stringChar) {
                    $inString = false;
                } elseif ($stringChar === '`' && $c === '$' && $next === '{') {
                    $outArr[] = '{';
                    $outCount++;
                    $templateStack[] = 0;
                    $inString = false;
                    $pos += 2;
                    $lastOutChar = '{';
                    $lastMeaningfulChar = '{';
                    continue;
                }
                $pos++;
                $lastOutChar = $c;
                $lastMeaningfulChar = $c;
                continue;
            }

            if ($inRegex) {
                $outArr[] = $c;
                $outCount++;
                if ($c === '\\' && $next !== null) {
                    $outArr[] = $next;
                    $outCount++;
                    $pos += 2;
                    continue;
                }
                if ($c === '/') {
                    $inRegex = false;
                }
                $pos++;
                $lastOutChar = $c;
                $lastMeaningfulChar = $c;
                continue;
            }

            if ($c === '{' && !empty($templateStack)) {
                $templateStack[count($templateStack) - 1]++;
            }

            if ($c === '}' && !empty($templateStack)) {
                if ($templateStack[count($templateStack) - 1] > 0) {
                    $templateStack[count($templateStack) - 1]--;
                } else {
                    array_pop($templateStack);
                    $inString = true;
                    $stringChar = '`';
                    $outArr[] = '}';
                    $outCount++;
                    $pos++;
                    $lastOutChar = '}';
                    $lastMeaningfulChar = '}';
                    continue;
                }
            }

            if ($c === '/' && $next === '*') {
                $inBlockComment = true;
                $pos += 2;
                continue;
            }

            if ($c === '/' && $next === '/') {
                $inLineComment = true;
                $pos += 2;
                continue;
            }

            if ($c === '"' || $c === "'" || $c === '`') {
                $inString = true;
                $stringChar = $c;
                $outArr[] = $c;
                $outCount++;
                $pos++;
                $lastOutChar = $c;
                $lastMeaningfulChar = $c;
                continue;
            }

            if ($c === '/') {
                $isRegex = strpos($regexStartersCh, $lastMeaningfulChar) !== false;
                if (!$isRegex) {
                    $tail = '';
                    $start = max(0, $outCount - 15);
                    for ($i = $start; $i < $outCount; $i++) {
                        $tail .= $outArr[$i];
                    }
                    if (preg_match('/(?:^|[^a-zA-Z0-9_$])(?:return|typeof|case|yield|throw|delete|in|do|instanceof|void|new|await)\s*$/', $tail)) {
                        $isRegex = true;
                    }
                }
                if ($isRegex) {
                    $inRegex = true;
                    $outArr[] = $c;
                    $outCount++;
                    $pos++;
                    $lastOutChar = $c;
                    $lastMeaningfulChar = $c;
                    continue;
                }
            }

            if ($c === ' ' || $c === "\t" || $c === "\n") {
                $isNewline = false;
                while ($pos < $len && ($js[$pos] === ' ' || $js[$pos] === "\t" || $js[$pos] === "\n")) {
                    if ($js[$pos] === "\n") {
                        $isNewline = true;
                    }
                    $pos++;
                }
                if ($isNewline && $safe) {
                    $lastIsAlphanum = ctype_alnum($lastOutChar) || $lastOutChar === '_' || $lastOutChar === '$';
                    if ($lastIsAlphanum || in_array($lastOutChar, ['}', ']', ')', "'", '"', '`', '+', '-'], true)) {
                        $outArr[] = "\n";
                        $outCount++;
                        $lastOutChar = "\n";
                        $lastMeaningfulChar = "\n";
                        continue;
                    }
                }
                $nextSig = ($pos < $len) ? $js[$pos] : null;
                $lastIsAlphanum = ctype_alnum($lastOutChar) || $lastOutChar === '_' || $lastOutChar === '$';
                $nextIsAlphanum = $nextSig && (ctype_alnum($nextSig) || $nextSig === '_' || $nextSig === '$');
                if ($lastIsAlphanum && $nextIsAlphanum) {
                    $outArr[] = ' ';
                    $outCount++;
                    $lastOutChar = ' ';
                } elseif (($lastOutChar === '+' && $nextSig === '+') || ($lastOutChar === '-' && $nextSig === '-')) {
                    $outArr[] = ' ';
                    $outCount++;
                    $lastOutChar = ' ';
                }
                continue;
            }

            $outArr[] = $c;
            $outCount++;
            $lastOutChar = $c;
            if ($c !== ' ' && $c !== "\n") {
                $lastMeaningfulChar = $c;
            }
            $pos++;
        }

        return implode('', $outArr);
    }
}
