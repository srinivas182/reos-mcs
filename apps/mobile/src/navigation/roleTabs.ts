/**
 * Bottom tabs for each role, exactly as in the mobile journey documents.
 * Sprint 3 picks the set from the signed-in person's active role.
 */
import {
  BookOpen,
  Building2,
  CircleCheck,
  FileText,
  GraduationCap,
  House,
  LayoutGrid,
  MessageSquare,
  Sparkles,
  Target,
  User,
  Users,
  Video,
  type LucideIcon,
} from 'lucide-react-native';

export type AppRole = 'candidate' | 'agent' | 'broker' | 'provider' | 'instructor' | 'mentor';

export type TabKey =
  | 'today'
  | 'learn'
  | 'practice'
  | 'ask'
  | 'deals'
  | 'mentors'
  | 'agents'
  | 'approvals'
  | 'recruit'
  | 'brokerage'
  | 'messages'
  | 'community'
  | 'profile'
  | 'classes'
  | 'sessions'
  | 'students'
  | 'mentees'
  | 'me';

export type TabSpec = { key: TabKey; icon: LucideIcon };

export const roleTabs: Record<AppRole, readonly TabSpec[]> = {
  candidate: [
    { key: 'today', icon: House },
    { key: 'learn', icon: BookOpen },
    { key: 'practice', icon: Target },
    { key: 'ask', icon: MessageSquare },
  ],
  agent: [
    { key: 'today', icon: House },
    { key: 'learn', icon: BookOpen },
    { key: 'ask', icon: Sparkles },
    { key: 'deals', icon: FileText },
    { key: 'mentors', icon: User },
  ],
  broker: [
    { key: 'today', icon: House },
    { key: 'agents', icon: Users },
    { key: 'approvals', icon: CircleCheck },
    { key: 'recruit', icon: Target },
    { key: 'brokerage', icon: Building2 },
  ],
  provider: [
    { key: 'today', icon: House },
    { key: 'agents', icon: Users },
    { key: 'messages', icon: MessageSquare },
    { key: 'community', icon: LayoutGrid },
    { key: 'profile', icon: User },
  ],
  instructor: [
    { key: 'today', icon: House },
    { key: 'classes', icon: GraduationCap },
    { key: 'sessions', icon: Video },
    { key: 'students', icon: Users },
    { key: 'me', icon: User },
  ],
  mentor: [
    { key: 'today', icon: House },
    { key: 'mentees', icon: Users },
    { key: 'sessions', icon: Video },
    { key: 'messages', icon: MessageSquare },
    { key: 'me', icon: User },
  ],
};
