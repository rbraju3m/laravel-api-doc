import { Text, Stack } from '@mantine/core';
import { CodeHighlight } from '@mantine/code-highlight';

/**
 * Builds a JSON example request body from body parameters.
 * Converts example strings to proper JSON types based on param type.
 */
function buildExampleBody(parameters) {
    const bodyParams = (parameters || []).filter((p) => p.location === 'body');

    if (bodyParams.length === 0) return null;

    const body = {};

    bodyParams.forEach((param) => {
        const name = param.name;
        const example = param.example || 'example_value';
        const type = (param.type || 'string').toLowerCase();

        // Convert string example to proper type
        if (type === 'integer' || type.includes('integer')) {
            body[name] = parseInt(example, 10) || 1;
        } else if (type === 'number' || type.includes('number') || type.includes('float') || type.includes('decimal')) {
            body[name] = parseFloat(example) || 10.5;
        } else if (type === 'boolean' || type.includes('boolean')) {
            body[name] = example === 'false' ? false : true;
        } else if (type === 'array' || type.includes('array')) {
            try {
                body[name] = JSON.parse(example);
            } catch {
                body[name] = [];
            }
        } else if (type === 'file' || type.includes('file')) {
            body[name] = '(binary file)';
        } else {
            body[name] = example;
        }
    });

    return body;
}

export default function RequestBodyExample({ parameters, httpMethod }) {
    // Only show for methods that typically have a request body
    if (!['POST', 'PUT', 'PATCH'].includes(httpMethod)) {
        return null;
    }

    const exampleBody = buildExampleBody(parameters);

    if (!exampleBody || Object.keys(exampleBody).length === 0) {
        return null;
    }

    return (
        <Stack gap="xs">
            <Text size="xs" c="dimmed">Content-Type: application/json</Text>
            <CodeHighlight
                code={JSON.stringify(exampleBody, null, 2)}
                language="json"
            />
        </Stack>
    );
}
