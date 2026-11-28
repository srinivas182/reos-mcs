import { loadFile, messageAgent, progressOf, tickItem } from './files';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

describe('my files on the phone (B17.1)', () => {
  it('ticks items, messages the agent, and reads nothing once access ended', async () => {
    mockApi.PUT.mockResolvedValueOnce({ data: { ticked: true } });
    await tickItem('s-1', 'doc:Inspection report', true);
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/coordinator/files/{share}/checklist', { params: { path: { share: 's-1' } }, body: { item: 'doc:Inspection report', done: true } });

    mockApi.POST.mockResolvedValueOnce({ error: { errors: { body: ["The agent hasn't turned on messages for this file."] } } });
    await expect(messageAgent('s-1', 'Hi')).rejects.toThrow("The agent hasn't turned on messages for this file.");
    mockApi.POST.mockResolvedValueOnce({ data: { data: { conversation: 'c-1' } } });
    await expect(messageAgent('s-1', 'Hi James')).resolves.toBe('c-1');

    mockApi.GET.mockResolvedValueOnce({ error: { message: 'Not Found' } });
    await expect(loadFile('s-1')).resolves.toBeNull();
    expect(progressOf({ progress: { done: 2, total: 4 } })).toBe(0.5);
    expect(progressOf({ progress: { done: 0, total: 0 } })).toBe(0);
  });
});
