import { Table, Badge, Text, Code } from '@mantine/core';

export default function ParameterTable({ parameters }) {
    if (!parameters || parameters.length === 0) {
        return <Text c="dimmed" size="sm">No parameters</Text>;
    }

    return (
        <Table striped highlightOnHover withTableBorder>
            <Table.Thead>
                <Table.Tr>
                    <Table.Th>Name</Table.Th>
                    <Table.Th>Location</Table.Th>
                    <Table.Th>Type</Table.Th>
                    <Table.Th>Required</Table.Th>
                    <Table.Th>Description</Table.Th>
                    <Table.Th>Rules</Table.Th>
                    <Table.Th>Example</Table.Th>
                </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
                {parameters.map((param) => (
                    <Table.Tr key={param.id}>
                        <Table.Td>
                            <Code>{param.name}</Code>
                        </Table.Td>
                        <Table.Td>
                            <Badge variant="light" size="xs" color={param.location === 'uri' ? 'violet' : 'cyan'}>
                                {param.location}
                            </Badge>
                        </Table.Td>
                        <Table.Td>
                            <Text size="sm">{param.type}</Text>
                        </Table.Td>
                        <Table.Td>
                            {param.required ? (
                                <Badge color="red" variant="light" size="xs">required</Badge>
                            ) : (
                                <Badge color="gray" variant="light" size="xs">optional</Badge>
                            )}
                        </Table.Td>
                        <Table.Td>
                            <Text size="xs" c="dimmed">{param.description || '—'}</Text>
                        </Table.Td>
                        <Table.Td>
                            <Text size="xs" c="dimmed">{param.rules || '—'}</Text>
                        </Table.Td>
                        <Table.Td>
                            <Code>{param.example || '—'}</Code>
                        </Table.Td>
                    </Table.Tr>
                ))}
            </Table.Tbody>
        </Table>
    );
}
