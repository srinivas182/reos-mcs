import { choiceFromSpeech, speakable } from './listen';

const options = ['At first substantive contact', 'At closing', 'Only when asked'];

describe('audio-only lessons (A14.2)', () => {
  it('hears which answer the learner said', () => {
    expect(choiceFromSpeech('two', options)).toBe(1);
    expect(choiceFromSpeech('Option 3', options)).toBe(2);
    expect(choiceFromSpeech('the first one', options)).toBe(0);
    expect(choiceFromSpeech('B', options)).toBe(1);
    expect(choiceFromSpeech('at closing I think', options)).toBe(1);
    expect(choiceFromSpeech('la segunda', options)).toBe(1);
    expect(choiceFromSpeech('opción tres', options)).toBe(2);
  });

  it('asks again when it is unclear', () => {
    expect(choiceFromSpeech('', options)).toBeNull();
    expect(choiceFromSpeech('seven', options)).toBeNull();
    expect(choiceFromSpeech('hmm let me think', options)).toBeNull();
  });

  it('splits long text at sentences for the phone to read', () => {
    const text = 'Disclose agency early. '.repeat(100);
    const pieces = speakable(text, 200);
    expect(pieces.every((p) => p.length <= 200)).toBe(true);
    expect(pieces.join(' ').split('Disclose').length - 1).toBe(100);
    expect(speakable('   ')).toEqual([]);
  });
});
