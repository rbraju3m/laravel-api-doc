import { Accordion, Badge, Text, Stack } from '@mantine/core';
import { CodeHighlight } from '@mantine/code-highlight';

const STATUS_COLORS = {
    200: 'green',
    201: 'green',
    204: 'green',
    401: 'red',
    403: 'red',
    404: 'orange',
    422: 'yellow',
    500: 'red',
};

export default function ResponsePanel({ responses }) {
    if (!responses || responses.length === 0) {
        return <Text c="dimmed" size="sm">No response examples</Text>;
    }

    return (
        <Accordion variant="separated">
            {responses.map((response) => (
                <Accordion.Item key={response.id} value={String(response.status_code)}>
                    <Accordion.Control>
                        <Badge
                            color={STATUS_COLORS[response.status_code] || 'gray'}
                            variant="filled"
                            size="md"
                            mr="sm"
                        >
                            {response.status_code}
                        </Badge>
                        <Text component="span" size="sm">{response.description}</Text>
                    </Accordion.Control>
                    <Accordion.Panel>
                        <Stack gap="xs">
                            <Text size="xs" c="dimmed">Content-Type: {response.content_type}</Text>
                            {response.example_body ? (
                                <CodeHighlight
                                    code={JSON.stringify(response.example_body, null, 2)}
                                    language="json"
                                />
                            ) : (
                                <Text size="sm" c="dimmed">No body</Text>
                            )}
                        </Stack>
                    </Accordion.Panel>
                </Accordion.Item>
            ))}
        </Accordion>
    );
}
