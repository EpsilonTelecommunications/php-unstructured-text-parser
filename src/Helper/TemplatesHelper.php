<?php

declare(strict_types = 1);

namespace aymanrb\UnstructuredTextParser\Helper;

use aymanrb\UnstructuredTextParser\Exception\InvalidTemplatesDirectoryException;

class TemplatesHelper
{
    private \FilesystemIterator $directoryIterator;

    public function __construct(string $templatesDir)
    {
        $this->directoryIterator = $this->createTemplatesDirIterator($templatesDir);
    }

    public function getTemplates(string $text, bool $findMatchingTemplate = false): array
    {
        if ($findMatchingTemplate) {
            return $this->findTemplate($text);
        }

        return $this->getAllValidTemplates();
    }

    private function createTemplatesDirIterator(string $iterableDirectoryPath): \FilesystemIterator
    {
        if (empty($iterableDirectoryPath) || !is_dir($iterableDirectoryPath)) {
            throw new InvalidTemplatesDirectoryException(
                'Invalid templates directory provided'
            );
        }

        return new \FilesystemIterator(rtrim($iterableDirectoryPath, '/'));
    }

    private function findTemplate(string $text): array
    {
        $matchedTemplate = [];
        $maxMatch = -1;

        foreach ($this->directoryIterator as $fileInfo) {
            $templateContent = file_get_contents($fileInfo->getPathname());

            // compare template against text to decide on similarity percentage
            similar_text($text, $templateContent, $matchPercentage);

            if ($matchPercentage > $maxMatch) {
                $maxMatch = $matchPercentage;
                $matchedTemplate = [$fileInfo->getPathname() => $this->prepareTemplate($templateContent)];
            }
        }

        return $matchedTemplate;
    }

    private function getAllValidTemplates(): array
    {
        $templates = [];
        foreach ($this->directoryIterator as $fileInfo) {
            if (!is_file($fileInfo->getPathname())) {
                continue;
            }

            $templateContent = file_get_contents($fileInfo->getPathname());
            $templates[$fileInfo->getPathname()] = $this->prepareTemplate($templateContent);
        }

        krsort($templates);

        return $templates;
    }

    private function prepareTemplate(string $templateText): string
    {
        $templateText = preg_quote($templateText, '/');

        // replace all {%Var:Pattern%} in the template with (?<Var>Pattern) regex vars
        $templateText =  preg_replace('/\\\{%([^%]+):(.*)%\\\}/U', '(?<$1>$2)', $templateText);

        // remove the regex escaped characters of the provided patterns
        $templateText = preg_replace_callback(
            '/(\(\?[^)]*)./',
            function ($matches) {
                return str_replace('\\', '', $matches[0]);
            },
            $templateText
        );

        // replace all {%Var%} in the template with (?<Var>.*) regex vars
        return preg_replace('/\\\{%(.*)%\\\}/U', '(?<$1>.*)', $templateText);
    }
}