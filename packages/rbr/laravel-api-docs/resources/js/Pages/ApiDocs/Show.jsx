import { Head, Link, useForm } from '@inertiajs/react';
import { Title, Text, Code, Group, Badge, Stack, Divider, Paper, Button } from '@mantine/core';
import Layout from '../../Components/ApiDocs/Layout';
import MethodBadge from '../../Components/ApiDocs/MethodBadge';
import ParameterTable from '../../Components/ApiDocs/ParameterTable';
import RequestBodyExample from '../../Components/ApiDocs/RequestBodyExample';
import ResponsePanel from '../../Components/ApiDocs/ResponsePanel';

export default function Show({ endpoint, title }) {
    const projectId = endpoint.group?.api_project_id;
    const backUrl = projectId ? `/docs/api/projects/${projectId}` : '/docs/api';
    const backLabel = projectId ? 'Back to Project' : 'Back to all endpoints';

    const { delete: destroy, processing } = useForm();

    const handleDelete = () => {
        if (confirm(`Delete ${endpoint.http_method} /${endpoint.uri}?`)) {
            destroy(`/docs/api/endpoints/${endpoint.id}`);
        }
    };

    const sidebar = (
        <Stack gap="xs">
            <Button
                component={Link}
                href={backUrl}
                variant="subtle"
                size="sm"
                fullWidth
                justify="flex-start"
            >
                {backLabel}
            </Button>
        </Stack>
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={`${endpoint.http_method} /${endpoint.uri} - ${title}`} />

            <Stack gap="lg">
                {/* Header */}
                <div>
                    <Group gap="sm" mb="xs" justify="space-between" wrap="nowrap">
                        <Group gap="sm" wrap="nowrap">
                            <MethodBadge method={endpoint.http_method} size="lg" />
                            <Title order={2} style={{ fontFamily: 'monospace' }}>
                                /{endpoint.uri}
                            </Title>
                        </Group>
                        <Button
                            color="red"
                            variant="subtle"
                            size="sm"
                            onClick={handleDelete}
                            loading={processing}
                        >
                            Delete
                        </Button>
                    </Group>

                    <Group gap="xs" mb="sm">
                        {endpoint.name && (
                            <Badge variant="light" color="gray">{endpoint.name}</Badge>
                        )}
                        {endpoint.is_authenticated && (
                            <Badge variant="light" color="yellow">Requires Authentication</Badge>
                        )}
                        {endpoint.is_closure && (
                            <Badge variant="light" color="violet">Closure Route</Badge>
                        )}
                        {endpoint.group && (
                            <Badge variant="light" color="cyan">{endpoint.group.name}</Badge>
                        )}
                    </Group>

                    {endpoint.description && (
                        <Text size="md">{endpoint.description}</Text>
                    )}
                </div>

                <Divider />

                {/* Controller Info */}
                {endpoint.controller_class && (
                    <Paper p="md" withBorder radius="md">
                        <Text size="sm" fw={600} mb="xs">Controller</Text>
                        <Code block>
                            {endpoint.controller_class}@{endpoint.controller_method}
                        </Code>
                    </Paper>
                )}

                {/* Middleware */}
                {endpoint.middleware && endpoint.middleware.length > 0 && (
                    <Paper p="md" withBorder radius="md">
                        <Text size="sm" fw={600} mb="xs">Middleware</Text>
                        <Group gap="xs">
                            {endpoint.middleware.map((m, i) => (
                                <Badge key={i} variant="outline" size="sm">{m}</Badge>
                            ))}
                        </Group>
                    </Paper>
                )}

                <Divider />

                {/* Parameters */}
                <div>
                    <Title order={4} mb="sm">Parameters</Title>
                    <ParameterTable parameters={endpoint.parameters} />
                </div>

                {/* Example Request Body */}
                {['POST', 'PUT', 'PATCH'].includes(endpoint.http_method) && endpoint.parameters?.some(p => p.location === 'body') && (
                    <>
                        <Divider />
                        <div>
                            <Title order={4} mb="sm">Example Request Body</Title>
                            <RequestBodyExample parameters={endpoint.parameters} httpMethod={endpoint.http_method} />
                        </div>
                    </>
                )}

                <Divider />

                {/* Responses */}
                <div>
                    <Title order={4} mb="sm">Responses</Title>
                    <ResponsePanel responses={endpoint.responses} />
                </div>
            </Stack>
        </Layout>
    );
}
