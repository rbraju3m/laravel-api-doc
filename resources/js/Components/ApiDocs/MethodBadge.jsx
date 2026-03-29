import { Badge } from '@mantine/core';

const METHOD_COLORS = {
    GET: 'blue',
    POST: 'green',
    PUT: 'orange',
    PATCH: 'yellow',
    DELETE: 'red',
    OPTIONS: 'gray',
    HEAD: 'gray',
};

export default function MethodBadge({ method, size = 'sm' }) {
    return (
        <Badge
            color={METHOD_COLORS[method] || 'gray'}
            variant="filled"
            size={size}
            style={{ minWidth: 65, fontFamily: 'monospace' }}
        >
            {method}
        </Badge>
    );
}
