import { TextInput } from '@mantine/core';

export default function SearchBar({ value, onChange }) {
    return (
        <TextInput
            placeholder="Search endpoints..."
            value={value}
            onChange={(e) => onChange(e.currentTarget.value)}
            mb="md"
        />
    );
}
