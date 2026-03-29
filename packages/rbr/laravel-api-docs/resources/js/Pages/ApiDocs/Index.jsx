import { useState, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Title, Text, Button, Group, Alert, Stack, Divider,
    Paper, SimpleGrid, ThemeIcon, Badge, Box, Card, RingProgress, Center,
} from '@mantine/core';
import Layout from '../../Components/ApiDocs/Layout';
import GroupNav from '../../Components/ApiDocs/GroupNav';
import SearchBar from '../../Components/ApiDocs/SearchBar';
import EndpointCard from '../../Components/ApiDocs/EndpointCard';
import ProjectCard from '../../Components/ApiDocs/ProjectCard';

function StatCard({ label, value, color, icon }) {
    return (
        <Paper withBorder radius="md" p="md" shadow="xs">
            <Group justify="space-between" wrap="nowrap">
                <div>
                    <Text size="xs" c="dimmed" tt="uppercase" fw={700}>{label}</Text>
                    <Text fw={700} size="xl" mt={4}>{value}</Text>
                </div>
                <ThemeIcon variant="light" color={color} size={48} radius="md">
                    <span style={{ fontSize: 22 }}>{icon}</span>
                </ThemeIcon>
            </Group>
        </Paper>
    );
}

function MethodDistribution({ groups }) {
    const methods = useMemo(() => {
        const counts = { GET: 0, POST: 0, PUT: 0, PATCH: 0, DELETE: 0 };
        groups.forEach(g => (g.endpoints || []).forEach(e => {
            if (counts[e.http_method] !== undefined) counts[e.http_method]++;
        }));
        return counts;
    }, [groups]);

    const total = Object.values(methods).reduce((a, b) => a + b, 0) || 1;
    const colors = { GET: 'blue', POST: 'green', PUT: 'orange', PATCH: 'yellow', DELETE: 'red' };

    return (
        <Paper withBorder radius="md" p="md" shadow="xs">
            <Text size="xs" c="dimmed" tt="uppercase" fw={700} mb="sm">HTTP Methods</Text>
            <Group gap="xs" wrap="wrap">
                {Object.entries(methods).map(([method, count]) => (
                    count > 0 && (
                        <Badge key={method} color={colors[method]} variant="light" size="lg" radius="sm">
                            {method}: {count}
                        </Badge>
                    )
                ))}
            </Group>
        </Paper>
    );
}

export default function Index({ groups, projects, title, description }) {
    const [search, setSearch] = useState('');
    const [activeGroupId, setActiveGroupId] = useState(null);
    const [generating, setGenerating] = useState(false);
    const { flash } = usePage().props;

    const totalEndpoints = useMemo(() => {
        return groups.reduce((acc, g) => acc + (g.endpoints?.length || 0), 0);
    }, [groups]);

    const authEndpoints = useMemo(() => {
        return groups.reduce((acc, g) =>
            acc + (g.endpoints || []).filter(e => e.is_authenticated).length, 0);
    }, [groups]);

    const filteredEndpoints = useMemo(() => {
        let endpoints = [];

        groups.forEach((group) => {
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
                endpoints.push(endpoint);
            });
        });

        return endpoints;
    }, [groups, search, activeGroupId]);

    const handleGenerate = () => {
        setGenerating(true);
        router.post('/docs/api/generate', {}, {
            onFinish: () => setGenerating(false),
        });
    };

    const sidebar = (
        <GroupNav
            groups={groups}
            activeGroupId={activeGroupId}
            onGroupClick={setActiveGroupId}
        />
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={title} />

            <Stack gap="lg">
                {/* Hero Section */}
                <Paper
                    radius="md"
                    p="xl"
                    style={{
                        background: 'linear-gradient(135deg, #228be6 0%, #15aabf 50%, #12b886 100%)',
                        color: 'white',
                    }}
                >
                    <Group justify="space-between" align="flex-start" wrap="wrap">
                        <div style={{ maxWidth: 600 }}>
                            <Title order={1} fw={800} style={{ letterSpacing: -1 }}>
                                {title}
                            </Title>
                            {description && (
                                <Text mt="sm" size="lg" style={{ opacity: 0.9 }}>
                                    {description}
                                </Text>
                            )}
                            <Text mt="xs" size="sm" style={{ opacity: 0.7 }}>
                                Auto-generated from your Laravel routes, controllers & FormRequests
                            </Text>
                        </div>
                        <Button
                            onClick={handleGenerate}
                            loading={generating}
                            variant="white"
                            color="dark"
                            size="md"
                            radius="md"
                        >
                            Regenerate Docs
                        </Button>
                    </Group>
                </Paper>

                {flash?.message && (
                    <Alert color="green" variant="light" withCloseButton radius="md">
                        {flash.message}
                    </Alert>
                )}

                {/* Stats Cards */}
                <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }} spacing="md">
                    <StatCard
                        label="Total Endpoints"
                        value={totalEndpoints}
                        color="blue"
                        icon="&#9670;"
                    />
                    <StatCard
                        label="Route Groups"
                        value={groups.length}
                        color="teal"
                        icon="&#9776;"
                    />
                    <StatCard
                        label="Auth Protected"
                        value={authEndpoints}
                        color="yellow"
                        icon="&#9919;"
                    />
                    <StatCard
                        label="Projects"
                        value={projects?.length || 0}
                        color="grape"
                        icon="&#9733;"
                    />
                </SimpleGrid>

                {/* Method Distribution */}
                {totalEndpoints > 0 && <MethodDistribution groups={groups} />}

                <Divider />

                {/* Search & Endpoints */}
                <div>
                    <Group justify="space-between" align="center" mb="md">
                        <Title order={3}>Endpoints</Title>
                        <Text size="sm" c="dimmed">
                            {filteredEndpoints.length} of {totalEndpoints} endpoint{totalEndpoints !== 1 ? 's' : ''}
                        </Text>
                    </Group>

                    <SearchBar value={search} onChange={setSearch} />

                    <Stack gap="sm" mt="md">
                        {filteredEndpoints.map((endpoint) => (
                            <EndpointCard key={endpoint.id} endpoint={endpoint} />
                        ))}
                    </Stack>

                    {filteredEndpoints.length === 0 && (
                        <Paper withBorder radius="md" p="xl" mt="md">
                            <Text ta="center" c="dimmed" size="lg">
                                {groups.length === 0
                                    ? 'No documentation generated yet. Click "Regenerate Docs" to scan your routes.'
                                    : 'No endpoints match your search.'}
                            </Text>
                        </Paper>
                    )}
                </div>

                {/* External Projects */}
                {projects && projects.length > 0 && (
                    <>
                        <Divider />
                        <div>
                            <Group justify="space-between" align="center" mb="md">
                                <Title order={3}>External Projects</Title>
                                <Button component={Link} href="/docs/api/projects" variant="subtle" size="sm">
                                    View All Projects
                                </Button>
                            </Group>
                            <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="md">
                                {projects.map((project) => (
                                    <ProjectCard key={project.id} project={project} />
                                ))}
                            </SimpleGrid>
                        </div>
                    </>
                )}
            </Stack>
        </Layout>
    );
}
