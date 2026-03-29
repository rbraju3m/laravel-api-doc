import { Head, Link } from '@inertiajs/react';
import {
    Title, Text, Button, Group, Stack, Paper, Avatar,
    Badge, SimpleGrid, ThemeIcon, Card, Divider, Box, Anchor,
    Container, Center, Flex, List, rem,
} from '@mantine/core';

const AUTHOR = {
    name: 'Rashedul Bari Raju',
    role: 'Full Stack Developer',
    email: 'rbraju3m@gmail.com',
    bio: 'Passionate software engineer specializing in Laravel, React, and modern web technologies. Creator of RBR Laravel API Doc — an intelligent documentation generator that auto-scans your Laravel projects to produce beautiful, browsable API docs.',
    avatar: '/images/author.jpg',
    skills: ['Laravel', 'PHP', 'React', 'JavaScript', 'Inertia.js', 'MySQL', 'REST API', 'Tailwind CSS', 'Mantine UI', 'Git'],
    social: {
        github: 'https://github.com/rbraju3m',
        linkedin: 'https://linkedin.com/in/rbraju3m',
        website: 'https://rbraju3m.com',
    },
};

const FEATURES = [
    {
        icon: '&#9881;',
        title: 'Auto Route Scanning',
        desc: 'Scans all Laravel routes, controllers & FormRequests automatically. Zero manual config needed.',
        color: 'blue',
    },
    {
        icon: '&#128196;',
        title: 'Rich Documentation',
        desc: 'Generates parameters, validation rules, response examples with realistic data from your models.',
        color: 'green',
    },
    {
        icon: '&#127760;',
        title: 'External Projects',
        desc: 'Scan and document external Laravel projects by pointing to their filesystem path.',
        color: 'orange',
    },
    {
        icon: '&#9998;',
        title: 'Manual Endpoints',
        desc: 'Add custom endpoints manually with full parameter, response, and auth configuration.',
        color: 'violet',
    },
    {
        icon: '&#128269;',
        title: 'Search & Filter',
        desc: 'Instantly search across all endpoints by URI, method, name or description.',
        color: 'cyan',
    },
    {
        icon: '&#127912;',
        title: 'Beautiful UI',
        desc: 'Modern interface built with React 19, Mantine UI v8, and Inertia.js for a seamless experience.',
        color: 'pink',
    },
];

function FeatureCard({ icon, title, desc, color }) {
    return (
        <Card shadow="sm" padding="lg" radius="md" withBorder h="100%">
            <ThemeIcon variant="light" color={color} size={50} radius="md" mb="md">
                <span style={{ fontSize: 24 }} dangerouslySetInnerHTML={{ __html: icon }} />
            </ThemeIcon>
            <Text fw={600} size="lg" mb={6}>{title}</Text>
            <Text size="sm" c="dimmed" lh={1.6}>{desc}</Text>
        </Card>
    );
}

export default function Home({ title }) {
    const currentYear = new Date().getFullYear();

    return (
        <>
            <Head title="Home - RBR Laravel API Doc" />

            <Box style={{ minHeight: '100vh', background: '#f8f9fa' }}>
                {/* Hero */}
                <Box
                    py={60}
                    px="md"
                    style={{
                        background: 'linear-gradient(135deg, #1a1b4b 0%, #228be6 50%, #15aabf 100%)',
                        color: 'white',
                    }}
                >
                    <Container size="lg">
                        <Flex
                            direction={{ base: 'column', sm: 'row' }}
                            align="center"
                            gap={40}
                        >
                            <Box style={{ flex: '0 0 auto' }}>
                                <Avatar
                                    src={AUTHOR.avatar}
                                    size={160}
                                    radius="50%"
                                    style={{
                                        border: '4px solid rgba(255,255,255,0.3)',
                                        boxShadow: '0 8px 32px rgba(0,0,0,0.3)',
                                    }}
                                />
                            </Box>
                            <Box style={{ flex: 1 }}>
                                <Badge variant="light" color="cyan" size="lg" radius="sm" mb="sm">
                                    rbrlaravelapidoc v1.0
                                </Badge>
                                <Title order={1} fw={800} size={rem(42)} style={{ letterSpacing: -1 }}>
                                    {AUTHOR.name}
                                </Title>
                                <Text size="xl" mt={4} style={{ opacity: 0.85 }}>
                                    {AUTHOR.role}
                                </Text>
                                <Text size="md" mt="sm" maw={550} style={{ opacity: 0.75, lineHeight: 1.7 }}>
                                    {AUTHOR.bio}
                                </Text>
                                <Group mt="lg" gap="sm">
                                    <Button
                                        component={Link}
                                        href="/docs/api"
                                        size="md"
                                        radius="md"
                                        variant="white"
                                        color="dark"
                                    >
                                        View API Docs
                                    </Button>
                                    <Button
                                        component="a"
                                        href={`mailto:${AUTHOR.email}`}
                                        size="md"
                                        radius="md"
                                        variant="outline"
                                        color="white"
                                        style={{ borderColor: 'rgba(255,255,255,0.4)' }}
                                    >
                                        Contact Me
                                    </Button>
                                </Group>
                            </Box>
                        </Flex>
                    </Container>
                </Box>

                {/* Author Details */}
                <Container size="lg" py={40}>
                    <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="lg">
                        {/* Contact Card */}
                        <Paper shadow="sm" radius="md" p="xl" withBorder>
                            <ThemeIcon variant="gradient" gradient={{ from: 'blue', to: 'cyan' }} size={44} radius="md" mb="md">
                                <span style={{ fontSize: 20 }}>&#9993;</span>
                            </ThemeIcon>
                            <Text fw={700} size="lg" mb="xs">Contact Info</Text>
                            <Stack gap={8}>
                                <Group gap="xs">
                                    <Text size="sm" c="dimmed" w={60}>Email:</Text>
                                    <Anchor href={`mailto:${AUTHOR.email}`} size="sm">{AUTHOR.email}</Anchor>
                                </Group>
                                <Group gap="xs">
                                    <Text size="sm" c="dimmed" w={60}>GitHub:</Text>
                                    <Anchor href={AUTHOR.social.github} target="_blank" size="sm">@rbraju3m</Anchor>
                                </Group>
                                <Group gap="xs">
                                    <Text size="sm" c="dimmed" w={60}>Web:</Text>
                                    <Anchor href={AUTHOR.social.website} target="_blank" size="sm">rbraju3m.com</Anchor>
                                </Group>
                            </Stack>
                        </Paper>

                        {/* Skills Card */}
                        <Paper shadow="sm" radius="md" p="xl" withBorder>
                            <ThemeIcon variant="gradient" gradient={{ from: 'orange', to: 'yellow' }} size={44} radius="md" mb="md">
                                <span style={{ fontSize: 20 }}>&#9733;</span>
                            </ThemeIcon>
                            <Text fw={700} size="lg" mb="xs">Tech Stack</Text>
                            <Group gap={6} mt="sm">
                                {AUTHOR.skills.map((skill) => (
                                    <Badge key={skill} variant="light" color="blue" size="md" radius="sm">
                                        {skill}
                                    </Badge>
                                ))}
                            </Group>
                        </Paper>

                        {/* Quick Links Card */}
                        <Paper shadow="sm" radius="md" p="xl" withBorder>
                            <ThemeIcon variant="gradient" gradient={{ from: 'grape', to: 'pink' }} size={44} radius="md" mb="md">
                                <span style={{ fontSize: 20 }}>&#128279;</span>
                            </ThemeIcon>
                            <Text fw={700} size="lg" mb="xs">Quick Links</Text>
                            <Stack gap={8} mt="sm">
                                <Button
                                    component={Link}
                                    href="/docs/api"
                                    variant="light"
                                    color="blue"
                                    fullWidth
                                    justify="flex-start"
                                    size="sm"
                                    leftSection={<span>&#123;&#125;</span>}
                                >
                                    API Documentation
                                </Button>
                                <Button
                                    component={Link}
                                    href="/docs/api/projects"
                                    variant="light"
                                    color="teal"
                                    fullWidth
                                    justify="flex-start"
                                    size="sm"
                                    leftSection={<span>&#128193;</span>}
                                >
                                    External Projects
                                </Button>
                                <Button
                                    component="a"
                                    href={AUTHOR.social.github}
                                    target="_blank"
                                    variant="light"
                                    color="dark"
                                    fullWidth
                                    justify="flex-start"
                                    size="sm"
                                    leftSection={<span>&#128187;</span>}
                                >
                                    GitHub Profile
                                </Button>
                            </Stack>
                        </Paper>
                    </SimpleGrid>
                </Container>

                {/* Features Section */}
                <Box py={50} px="md" style={{ background: 'white' }}>
                    <Container size="lg">
                        <Center mb={40}>
                            <div style={{ textAlign: 'center' }}>
                                <Badge variant="light" color="blue" size="lg" radius="sm" mb="sm">
                                    Features
                                </Badge>
                                <Title order={2} fw={800}>
                                    Why RBR Laravel API Doc?
                                </Title>
                                <Text c="dimmed" mt="sm" maw={500} mx="auto">
                                    A powerful, zero-config API documentation generator built specifically for Laravel developers.
                                </Text>
                            </div>
                        </Center>

                        <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="lg">
                            {FEATURES.map((f, i) => (
                                <FeatureCard key={i} {...f} />
                            ))}
                        </SimpleGrid>
                    </Container>
                </Box>

                {/* CTA Section */}
                <Box py={50} px="md">
                    <Container size="sm">
                        <Paper
                            radius="lg"
                            p="xl"
                            style={{
                                background: 'linear-gradient(135deg, #228be6 0%, #15aabf 50%, #12b886 100%)',
                                color: 'white',
                                textAlign: 'center',
                            }}
                        >
                            <Title order={2} fw={700} mb="sm">Ready to explore?</Title>
                            <Text size="md" mb="lg" style={{ opacity: 0.85 }}>
                                View your auto-generated API documentation now.
                            </Text>
                            <Group justify="center" gap="md">
                                <Button
                                    component={Link}
                                    href="/docs/api"
                                    size="lg"
                                    radius="md"
                                    variant="white"
                                    color="dark"
                                >
                                    Open API Docs
                                </Button>
                                <Button
                                    component={Link}
                                    href="/docs/api/projects"
                                    size="lg"
                                    radius="md"
                                    variant="outline"
                                    color="white"
                                    style={{ borderColor: 'rgba(255,255,255,0.5)' }}
                                >
                                    Manage Projects
                                </Button>
                            </Group>
                        </Paper>
                    </Container>
                </Box>

                {/* Footer */}
                <Box py="lg" px="md" style={{ borderTop: '1px solid #dee2e6' }}>
                    <Container size="lg">
                        <Flex
                            justify="space-between"
                            align="center"
                            direction={{ base: 'column', sm: 'row' }}
                            gap="sm"
                        >
                            <Group gap="xs">
                                <ThemeIcon variant="gradient" gradient={{ from: 'blue', to: 'cyan' }} size="sm" radius="md">
                                    <span style={{ fontSize: 10, fontWeight: 700 }}>R</span>
                                </ThemeIcon>
                                <Text size="sm" fw={600}>RBR Laravel API Doc</Text>
                            </Group>
                            <Text size="xs" c="dimmed">
                                &copy; {currentYear} Rashedul Bari Raju. All rights reserved.
                            </Text>
                            <Group gap="md">
                                <Anchor href={AUTHOR.social.github} target="_blank" size="xs" c="dimmed">GitHub</Anchor>
                                <Anchor href={AUTHOR.social.linkedin} target="_blank" size="xs" c="dimmed">LinkedIn</Anchor>
                                <Anchor href={`mailto:${AUTHOR.email}`} size="xs" c="dimmed">Email</Anchor>
                            </Group>
                        </Flex>
                    </Container>
                </Box>
            </Box>
        </>
    );
}
