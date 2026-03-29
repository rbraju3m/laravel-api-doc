import { Head, Link, useForm } from '@inertiajs/react';
import { Title, Stack, Button } from '@mantine/core';
import Layout from '../../../Components/ApiDocs/Layout';
import EndpointForm from '../../../Components/ApiDocs/EndpointForm';

export default function Edit({ project, endpoint, title }) {
    const { data, setData, put, processing, errors } = useForm({
        group_name: endpoint.group?.name || '',
        http_method: endpoint.http_method,
        uri: endpoint.uri,
        name: endpoint.name || '',
        description: endpoint.description || '',
        is_authenticated: endpoint.is_authenticated || false,
        parameters: (endpoint.parameters || []).map((p) => ({
            name: p.name,
            location: p.location,
            type: p.type,
            required: p.required,
            description: p.description || '',
            rules: p.rules || '',
            example: p.example || '',
        })),
        responses: (endpoint.responses || []).map((r) => ({
            status_code: r.status_code,
            description: r.description || '',
            content_type: r.content_type || 'application/json',
            example_body: r.example_body ? JSON.stringify(r.example_body, null, 2) : '',
        })),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/docs/api/projects/${project.id}/endpoints/${endpoint.id}`);
    };

    const sidebar = (
        <Stack gap="xs">
            <Button
                component={Link}
                href={`/docs/api/projects/${project.id}`}
                variant="subtle"
                size="sm"
                fullWidth
                justify="flex-start"
            >
                Back to {project.name}
            </Button>
        </Stack>
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={`Edit Endpoint - ${project.name} - ${title}`} />

            <Stack gap="md">
                <Title order={2}>Edit Endpoint</Title>

                <EndpointForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Update Endpoint"
                />
            </Stack>
        </Layout>
    );
}
