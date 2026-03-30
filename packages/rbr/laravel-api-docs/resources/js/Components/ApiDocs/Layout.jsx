import { AppShell, Title, Group, Burger, ScrollArea, NavLink, Divider, Text, Box, Anchor, ThemeIcon } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { Link, usePage } from '@inertiajs/react';

export default function Layout({ title, sidebar, children }) {
    const [opened, { toggle }] = useDisclosure();
    const { url, apiDocsConfig } = usePage().props;
    const routePrefix = apiDocsConfig?.route_prefix || 'docs/api';
    const normalizedPrefix = routePrefix.startsWith('/') ? routePrefix : `/${routePrefix}`;
    const currentYear = new Date().getFullYear();

    return (
        <AppShell
            header={{ height: 60 }}
            navbar={{ width: 280, breakpoint: 'sm', collapsed: { mobile: !opened } }}
            footer={{ height: 50 }}
            padding="md"
        >
            <AppShell.Header>
                <Group h="100%" px="md" justify="space-between">
                    <Group>
                        <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
                        <ThemeIcon variant="gradient" gradient={{ from: 'blue', to: 'cyan' }} size="md" radius="md">
                            <span style={{ fontSize: 14, fontWeight: 700 }}>R</span>
                        </ThemeIcon>
                        <Title order={3} style={{ letterSpacing: -0.5 }}>
                            {title || apiDocsConfig?.title || 'RBR API Docs'}
                        </Title>
                    </Group>
                    <Text size="xs" c="dimmed" visibleFrom="sm">
                        Powered by RBR Laravel API Doc
                    </Text>
                </Group>
            </AppShell.Header>

            <AppShell.Navbar p="md">
                <AppShell.Section>
                    <NavLink
                        component={Link}
                        href="/"
                        label="Home"
                        leftSection={<span style={{ fontSize: 16 }}>&#8962;</span>}
                        active={url === '/'}
                        mb={4}
                    />
                    <NavLink
                        component={Link}
                        href={normalizedPrefix}
                        label="API Documentation"
                        leftSection={<span style={{ fontSize: 16 }}>&#123;&#125;</span>}
                        active={url.startsWith(normalizedPrefix)}
                        defaultOpened={url.startsWith(normalizedPrefix)}
                        mb={4}
                    >
                        <NavLink
                            component={Link}
                            href={normalizedPrefix}
                            label="Local Docs"
                            active={url === normalizedPrefix}
                            mb={2}
                        />
                        <NavLink
                            component={Link}
                            href={`${normalizedPrefix}/projects`}
                            label="Projects"
                            active={url.startsWith(`${normalizedPrefix}/projects`)}
                            mb={2}
                        />
                    </NavLink>
                    <Divider my="sm" />
                </AppShell.Section>
                <AppShell.Section grow component={ScrollArea}>
                    {sidebar}
                </AppShell.Section>
                <AppShell.Section>
                    <Divider my="sm" />
                    <Text size="xs" c="dimmed" ta="center">
                        rbrlaravelapidoc v1.0
                    </Text>
                </AppShell.Section>
            </AppShell.Navbar>

            <AppShell.Main>
                {children}
            </AppShell.Main>

            <AppShell.Footer p="xs">
                <Group justify="space-between" px="md" h="100%">
                    <Text size="xs" c="dimmed">
                        &copy; {currentYear} RBR Laravel API Doc. All rights reserved.
                    </Text>
                    <Text size="xs" c="dimmed">
                        Built with Laravel, React & Mantine
                    </Text>
                </Group>
            </AppShell.Footer>
        </AppShell>
    );
}
