import { Head, Link, useForm } from '@inertiajs/react';
import { Title, Stack, Button } from '@mantine/core';
import Layout from '../../../Components/ApiDocs/Layout';
import EndpointForm from '../../../Components/ApiDocs/EndpointForm';

export default function Create({ project, title }) {
    const { data, setData, post, processing, errors } = useForm({
        group_name: '',
        http_method: 'GET',
        uri: '',
        name: '',
        description: '',
        is_authenticated: false,
        parameters: [],
        responses: [],
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/docs/api/projects/${project.id}/endpoints`);
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
            <Head title={`Add Endpoint - ${project.name} - ${title}`} />

            <Stack gap="md">
                <Title order={2}>Add Endpoint to {project.name}</Title>

                <EndpointForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Create Endpoint"
                />
            </Stack>
        </Layout>
    );
}
