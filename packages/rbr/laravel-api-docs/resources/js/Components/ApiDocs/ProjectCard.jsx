import { Card, Group, Text, Badge } from '@mantine/core';
import { Link } from '@inertiajs/react';

export default function ProjectCard({ project }) {
    return (
        <Card
            shadow="xs"
            padding="md"
            radius="md"
            withBorder
            mb="sm"
            component={Link}
            href={`/docs/api/projects/${project.id}`}
            style={{ textDecoration: 'none', cursor: 'pointer' }}
        >
            <Group justify="space-between" wrap="nowrap">
                <div>
                    <Text fw={600}>{project.name}</Text>
                    <Text size="xs" c="dimmed">{project.base_url}</Text>
                </div>
                <Badge color="teal" variant="light" size="sm">
                    {project.endpoints_count || 0} endpoint{(project.endpoints_count || 0) !== 1 ? 's' : ''}
                </Badge>
            </Group>
            {project.description && (
                <Text size="sm" c="dimmed" mt="xs" lineClamp={2}>
                    {project.description}
                </Text>
            )}
        </Card>
    );
}
