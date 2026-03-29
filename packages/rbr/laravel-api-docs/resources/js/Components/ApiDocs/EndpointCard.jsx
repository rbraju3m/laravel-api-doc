import { Card, Group, Text, Code, Badge, Stack, ActionIcon } from '@mantine/core';
import { Link, router } from '@inertiajs/react';
import MethodBadge from './MethodBadge';

export default function EndpointCard({ endpoint, projectId }) {
    const href = projectId
        ? `/docs/api/endpoints/${endpoint.id}`
        : `/docs/api/endpoints/${endpoint.id}`;

    const handleDelete = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Delete this endpoint?')) {
            router.delete(`/docs/api/projects/${projectId}/endpoints/${endpoint.id}`);
        }
    };

    return (
        <Card
            shadow="xs"
            padding="md"
            radius="md"
            withBorder
            mb="sm"
            component={Link}
            href={href}
            style={{ textDecoration: 'none', cursor: 'pointer' }}
        >
            <Group justify="space-between" wrap="nowrap">
                <Group gap="sm" wrap="nowrap">
                    <MethodBadge method={endpoint.http_method} />
                    <Code style={{ fontSize: '0.9rem' }}>/{endpoint.uri}</Code>
                </Group>
                <Group gap="xs">
                    {endpoint.is_authenticated && (
                        <Badge color="yellow" variant="light" size="xs">auth</Badge>
                    )}
                    {endpoint.name && (
                        <Badge color="gray" variant="light" size="xs">{endpoint.name}</Badge>
                    )}
                    {projectId && (
                        <>
                            <Badge
                                color="blue"
                                variant="light"
                                size="xs"
                                component={Link}
                                href={`/docs/api/projects/${projectId}/endpoints/${endpoint.id}/edit`}
                                onClick={(e) => e.stopPropagation()}
                                style={{ cursor: 'pointer' }}
                            >
                                Edit
                            </Badge>
                            <Badge
                                color="red"
                                variant="light"
                                size="xs"
                                onClick={handleDelete}
                                style={{ cursor: 'pointer' }}
                            >
                                Delete
                            </Badge>
                        </>
                    )}
                </Group>
            </Group>
            {endpoint.description && (
                <Text size="sm" c="dimmed" mt="xs" lineClamp={2}>
                    {endpoint.description}
                </Text>
            )}
        </Card>
    );
}
