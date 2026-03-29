import { Head, Link, usePage } from '@inertiajs/react';
import { Title, Text, Button, Group, Alert, Stack } from '@mantine/core';
import Layout from '../../../Components/ApiDocs/Layout';
import ProjectCard from '../../../Components/ApiDocs/ProjectCard';

export default function Index({ projects, title }) {
    const { flash } = usePage().props;

    const sidebar = (
        <Stack gap="xs">
            <Button
                component={Link}
                href="/docs/api"
                variant="subtle"
                size="sm"
                fullWidth
                justify="flex-start"
            >
                Back to Local Docs
            </Button>
        </Stack>
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={`External Projects - ${title}`} />

            <Stack gap="md">
                <Group justify="space-between" align="flex-start">
                    <div>
                        <Title order={2}>External Projects</Title>
                        <Text c="dimmed" mt={4}>Manage API documentation for external services</Text>
                    </div>
                    <Button component={Link} href="/docs/api/projects/create">
                        Add Project
                    </Button>
                </Group>

                {flash?.message && (
                    <Alert color="green" variant="light" withCloseButton>
                        {flash.message}
                    </Alert>
                )}

                {projects.map((project) => (
                    <ProjectCard key={project.id} project={project} />
                ))}

                {projects.length === 0 && (
                    <Text ta="center" c="dimmed" mt="xl">
                        No external projects yet. Click "Add Project" to get started.
                    </Text>
                )}
            </Stack>
        </Layout>
    );
}
