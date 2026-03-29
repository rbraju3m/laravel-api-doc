import { NavLink } from '@mantine/core';

export default function GroupNav({ groups, activeGroupId, onGroupClick }) {
    return (
        <>
            <NavLink
                label="All Endpoints"
                active={!activeGroupId}
                onClick={() => onGroupClick(null)}
                mb={4}
            />
            {groups.map((group) => (
                <NavLink
                    key={group.id}
                    label={group.name}
                    description={`/${group.prefix} (${group.endpoints?.length || 0})`}
                    active={activeGroupId === group.id}
                    onClick={() => onGroupClick(group.id)}
                    mb={4}
                />
            ))}
        </>
    );
}
