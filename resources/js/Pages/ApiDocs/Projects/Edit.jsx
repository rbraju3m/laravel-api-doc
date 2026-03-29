import { Head, Link, useForm } from '@inertiajs/react';
import { Title, TextInput, Textarea, Button, Group, Stack } from '@mantine/core';
import Layout from '../../../Components/ApiDocs/Layout';

export default function Edit({ project, title }) {
    const { data, setData, put, processing, errors, transform } = useForm({
        name: project.name,
        base_url: project.base_url || '',
        description: project.description || '',
        project_path: project.project_path || '',
        exclude_prefixes: project.exclude_prefixes?.join(', ') || '',
    });

    transform((data) => ({
        ...data,
        exclude_prefixes: data.exclude_prefixes
            ? data.exclude_prefixes.split(',').map((s) => s.trim()).filter(Boolean)
            : [],
    }));

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/docs/api/projects/${project.id}`);
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
                Back to Project
            </Button>
        </Stack>
    );

    return (
        <Layout title={title} sidebar={sidebar}>
            <Head title={`Edit ${project.name} - ${title}`} />

            <Stack gap="md">
                <Title order={2}>Edit Project</Title>

                <form onSubmit={handleSubmit}>
                    <Stack gap="md">
                        <TextInput
                            label="Project Name"
                            required
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                        />
                        <TextInput
                            label="Base URL"
                            value={data.base_url}
                            onChange={(e) => setData('base_url', e.target.value)}
                            error={errors.base_url}
                        />
                        <TextInput
                            label="Laravel Project Path"
                            placeholder="/var/www/html/my-laravel-project"
                            description="Filesystem path to a Laravel project for auto-generating docs"
                            value={data.project_path}
                            onChange={(e) => setData('project_path', e.target.value)}
                            error={errors.project_path}
                        />
                        <TextInput
                            label="Exclude Prefixes"
                            placeholder="e.g. admin, internal, legacy/v1"
                            description="Comma-separated route prefixes to exclude (in addition to defaults like _debugbar, _ignition, sanctum)"
                            value={data.exclude_prefixes}
                            onChange={(e) => setData('exclude_prefixes', e.target.value)}
                            error={errors.exclude_prefixes}
                        />
                        <Textarea
                            label="Description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            error={errors.description}
                        />
                        <Group justify="flex-end">
                            <Button variant="subtle" component={Link} href={`/docs/api/projects/${project.id}`}>Cancel</Button>
                            <Button type="submit" loading={processing}>Update Project</Button>
                        </Group>
                    </Stack>
                </form>
            </Stack>
        </Layout>
    );
}
