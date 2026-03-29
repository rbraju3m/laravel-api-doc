import { useState, useMemo, useRef } from 'react';
import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import { Title, Text, Button, Group, Alert, Stack, Code } from '@mantine/core';
import Layout from '../../../Components/ApiDocs/Layout';
import GroupNav from '../../../Components/ApiDocs/GroupNav';
import SearchBar from '../../../Components/ApiDocs/SearchBar';
import EndpointCard from '../../../Components/ApiDocs/EndpointCard';

export default function Show({ project, title }) {
    const [search, setSearch] = useState('');
    const [activeGroupId, setActiveGroupId] = useState(null);
    const { flash } = usePage().props;

    const filteredEndpoints = useMemo(() => {
        let endpoints = [];

        (project.groups || []).forEach((group) => {
            if (activeGroupId && group.id !== activeGroupId) return;

            (group.endpoints || []).forEach((endpoint) => {
                if (search) {
                    const q = search.toLowerCase();
                    const matches =
                        endpoint.uri.toLowerCase().includes(q) ||
                        endpoint.http_method.toLowerCase().includes(q) ||
                        (endpoint.name && endpoint.name.toLowerCase().includes(q)) ||
                        (endpoint.description && endpoint.description.toLowerCase().includes(q));
                    if (!matches) return;
                }
                endpoints.push({ ...endpoint, _projectId: project.id });
            });
        });

        return endpoints;
    }, [project.groups, search, activeGroupId]);

    const { post: postGenerate, processing: generating } = useForm({});

    const handleGenerate = () => {
        postGenerate(`/docs/api/projects/${project.id}/generate`);
    };

    const handleDelete = () => {
        if (confirm(`Delete project "${project.name}" and all its endpoints?`)) {
            router.delete(`/docs/api/projects/${project.id}`);
        }
    };

    const sidebar = (
        <>
            <Button
                component={Link}
                href="/docs/api/projects"
                variant="subtle"
                size="sm"
                fullWidth
                justify="flex-start"
                mb="md"
            >
                Back to Projects
            </Button>
            <GroupNav
                groups={project.groups || []}
                activeGroupId={activeGroupId}
                onGroupClick={setActiveGroupId}
            />
        </>
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={`${project.name} - ${title}`} />

            <Stack gap="md">
                <Group justify="space-between" align="flex-start">
                    <div>
                        <Title order={2}>{project.name}</Title>
                        <Text size="sm" c="dimmed"><Code>{project.base_url}</Code></Text>
                        {project.description && <Text c="dimmed" mt={4}>{project.description}</Text>}
                    </div>
                    <Group>
                        {project.project_path && (
                            <Button
                                variant="filled"
                                onClick={handleGenerate}
                                loading={generating}
                            >
                                Generate Docs
                            </Button>
                        )}
                        <Button
                            component={Link}
                            href={`/docs/api/projects/${project.id}/endpoints/create`}
                            variant="light"
                        >
                            Add Endpoint
                        </Button>
                        <Button
                            component={Link}
                            href={`/docs/api/projects/${project.id}/edit`}
                            variant="subtle"
                        >
                            Edit Project
                        </Button>
                        <Button
                            variant="subtle"
                            color="red"
                            onClick={handleDelete}
                        >
                            Delete
                        </Button>
                    </Group>
                </Group>

                {flash?.message && (
                    <Alert color="green" variant="light" withCloseButton>
                        {flash.message}
                    </Alert>
                )}

                <SearchBar value={search} onChange={setSearch} />

                <Text size="sm" c="dimmed">
                    {filteredEndpoints.length} endpoint{filteredEndpoints.length !== 1 ? 's' : ''}
                </Text>

                {filteredEndpoints.map((endpoint) => (
                    <EndpointCard key={endpoint.id} endpoint={endpoint} projectId={project.id} />
                ))}

                {filteredEndpoints.length === 0 && (
                    <Text ta="center" c="dimmed" mt="xl">
                        {(project.groups || []).length === 0
                            ? 'No endpoints yet. Click "Add Endpoint" to define your first API endpoint.'
                            : 'No endpoints match your search.'}
                    </Text>
                )}
            </Stack>
        </Layout>
    );
}
