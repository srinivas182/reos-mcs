import type { Meta, StoryObj } from '@storybook/react-vite';
import { AiPlan } from './ai-plan';
import { BuilderSettingsCard } from './builder-settings';
import { course, module, modules, roles, settings, teaching } from './fixtures';
import { LessonList } from './lesson-list';
import { ModuleList } from './module-list';
import { SeatTimeNotice } from './seat-time';
import { SourceMaterial } from './source-material';
import { TeachingCard } from './teaching-card';

const meta = {
    title: 'Instructor/Course builder',
    component: SourceMaterial,
    args: { moduleId: 1, sources: module.sources, roles },
} satisfies Meta<typeof SourceMaterial>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 43: a script that's been read, a recorded class, and slides still being read. */
export const Sources: Story = {};

export const NoSources: Story = { args: { sources: [] } };

export const WhatAiWillMake: Story = {
    render: () => <AiPlan plan={module.plan} />,
};

export const Settings: Story = {
    render: () => (
        <BuilderSettingsCard
            courseId={10}
            settings={settings}
            presenters={[{ value: 'instructor:7', label: 'Dr. Rachel Hughes' }]}
        />
    ),
};

export const Lessons: Story = {
    render: () => <LessonList moduleId={1} lessons={module.lessons} />,
};

export const Modules: Story = {
    render: () => <ModuleList courseId={10} modules={modules} current={1} />,
};

export const SeatTime: Story = {
    render: () => <SeatTimeNotice course={course} />,
};

/** The instructor's dashboard (screen 42). */
export const Dashboard: Story = {
    render: () => <TeachingCard teaching={teaching} />,
};
