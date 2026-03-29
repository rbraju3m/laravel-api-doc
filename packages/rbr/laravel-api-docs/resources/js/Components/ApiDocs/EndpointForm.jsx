import { TextInput, Select, Textarea, Switch, Button, Group, Stack, Title, ActionIcon, Paper, Text } from '@mantine/core';

const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'].map(m => ({ value: m, label: m }));
const LOCATIONS = ['query', 'body', 'uri'].map(l => ({ value: l, label: l }));
const PARAM_TYPES = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'file'].map(t => ({ value: t, label: t }));

export default function EndpointForm({ data, setData, errors, processing, onSubmit, submitLabel = 'Save' }) {
    const addParameter = () => {
        setData('parameters', [
            ...(data.parameters || []),
            { name: '', location: 'body', type: 'string', required: false, description: '', rules: '', example: '' },
        ]);
    };

    const updateParameter = (index, field, value) => {
        const params = [...(data.parameters || [])];
        params[index] = { ...params[index], [field]: value };
        setData('parameters', params);
    };

    const removeParameter = (index) => {
        setData('parameters', (data.parameters || []).filter((_, i) => i !== index));
    };

    const addResponse = () => {
        setData('responses', [
            ...(data.responses || []),
            { status_code: 200, description: '', content_type: 'application/json', example_body: '' },
        ]);
    };

    const updateResponse = (index, field, value) => {
        const resps = [...(data.responses || [])];
        resps[index] = { ...resps[index], [field]: value };
        setData('responses', resps);
    };

    const removeResponse = (index) => {
        setData('responses', (data.responses || []).filter((_, i) => i !== index));
    };

    return (
        <form onSubmit={onSubmit}>
            <Stack gap="md">
                <TextInput
                    label="Group Name"
                    placeholder="e.g. Users, Auth, Orders"
                    required
                    value={data.group_name}
                    onChange={(e) => setData('group_name', e.target.value)}
                    error={errors.group_name}
                />

                <Group grow>
                    <Select
                        label="HTTP Method"
                        data={HTTP_METHODS}
                        required
                        value={data.http_method}
                        onChange={(value) => setData('http_method', value)}
                        error={errors.http_method}
                    />
                    <TextInput
                        label="URI"
                        placeholder="e.g. users/{id}/posts"
                        required
                        value={data.uri}
                        onChange={(e) => setData('uri', e.target.value)}
                        error={errors.uri}
                    />
                </Group>

                <TextInput
                    label="Route Name"
                    placeholder="e.g. users.posts.index (optional)"
                    value={data.name || ''}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                />

                <Textarea
                    label="Description"
                    placeholder="What does this endpoint do?"
                    value={data.description || ''}
                    onChange={(e) => setData('description', e.target.value)}
                    error={errors.description}
                />

                <Switch
                    label="Requires Authentication"
                    checked={data.is_authenticated || false}
                    onChange={(e) => setData('is_authenticated', e.currentTarget.checked)}
                />

                {/* Parameters */}
                <div>
                    <Group justify="space-between" mb="xs">
                        <Title order={5}>Parameters</Title>
                        <Button variant="light" size="xs" onClick={addParameter}>Add Parameter</Button>
                    </Group>
                    {(data.parameters || []).map((param, index) => (
                        <Paper key={index} p="sm" withBorder mb="xs">
                            <Group justify="flex-end" mb="xs">
                                <Button variant="subtle" color="red" size="xs" onClick={() => removeParameter(index)}>Remove</Button>
                            </Group>
                            <Group grow mb="xs">
                                <TextInput
                                    label="Name"
                                    required
                                    value={param.name}
                                    onChange={(e) => updateParameter(index, 'name', e.target.value)}
                                    error={errors[`parameters.${index}.name`]}
                                />
                                <Select
                                    label="Location"
                                    data={LOCATIONS}
                                    required
                                    value={param.location}
                                    onChange={(value) => updateParameter(index, 'location', value)}
                                />
                                <Select
                                    label="Type"
                                    data={PARAM_TYPES}
                                    required
                                    value={param.type}
                                    onChange={(value) => updateParameter(index, 'type', value)}
                                />
                            </Group>
                            <Group grow mb="xs">
                                <TextInput
                                    label="Description"
                                    value={param.description || ''}
                                    onChange={(e) => updateParameter(index, 'description', e.target.value)}
                                />
                                <TextInput
                                    label="Example"
                                    value={param.example || ''}
                                    onChange={(e) => updateParameter(index, 'example', e.target.value)}
                                />
                            </Group>
                            <Group>
                                <Switch
                                    label="Required"
                                    checked={param.required || false}
                                    onChange={(e) => updateParameter(index, 'required', e.currentTarget.checked)}
                                />
                                <TextInput
                                    label="Validation Rules"
                                    placeholder="e.g. max:255"
                                    value={param.rules || ''}
                                    onChange={(e) => updateParameter(index, 'rules', e.target.value)}
                                    style={{ flex: 1 }}
                                />
                            </Group>
                        </Paper>
                    ))}
                    {(data.parameters || []).length === 0 && (
                        <Text size="sm" c="dimmed">No parameters added yet.</Text>
                    )}
                </div>

                {/* Responses */}
                <div>
                    <Group justify="space-between" mb="xs">
                        <Title order={5}>Responses</Title>
                        <Button variant="light" size="xs" onClick={addResponse}>Add Response</Button>
                    </Group>
                    {(data.responses || []).map((resp, index) => (
                        <Paper key={index} p="sm" withBorder mb="xs">
                            <Group justify="flex-end" mb="xs">
                                <Button variant="subtle" color="red" size="xs" onClick={() => removeResponse(index)}>Remove</Button>
                            </Group>
                            <Group grow mb="xs">
                                <TextInput
                                    label="Status Code"
                                    type="number"
                                    required
                                    value={resp.status_code}
                                    onChange={(e) => updateResponse(index, 'status_code', parseInt(e.target.value) || '')}
                                    error={errors[`responses.${index}.status_code`]}
                                />
                                <TextInput
                                    label="Description"
                                    value={resp.description || ''}
                                    onChange={(e) => updateResponse(index, 'description', e.target.value)}
                                />
                                <TextInput
                                    label="Content Type"
                                    value={resp.content_type || 'application/json'}
                                    onChange={(e) => updateResponse(index, 'content_type', e.target.value)}
                                />
                            </Group>
                            <Textarea
                                label="Example Body (JSON)"
                                placeholder='{"key": "value"}'
                                autosize
                                minRows={3}
                                value={resp.example_body || ''}
                                onChange={(e) => updateResponse(index, 'example_body', e.target.value)}
                                error={errors[`responses.${index}.example_body`]}
                            />
                        </Paper>
                    ))}
                    {(data.responses || []).length === 0 && (
                        <Text size="sm" c="dimmed">No responses added yet.</Text>
                    )}
                </div>

                <Group justify="flex-end">
                    <Button type="submit" loading={processing}>{submitLabel}</Button>
                </Group>
            </Stack>
        </form>
    );
}
