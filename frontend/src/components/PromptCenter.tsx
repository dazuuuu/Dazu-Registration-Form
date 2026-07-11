import React, { useState } from 'react';
import { GoogleGenAI } from "@google/genai";

// System instruction for the AI
const systemInstruction = `You are an expert Prompt Engineer. Your task is to take a user's simple idea or goal and expand it into a detailed, well-structured, and highly effective prompt. This generated prompt will be used by the user in another advanced generative AI model (like an image generator, a code generator, or a text model).

When you generate a prompt, you must:
1.  **Be Specific and Detailed:** Add concrete details that the user might not have thought of. If they say "a logo for a coffee shop," you must expand on this with styles, colors, and concepts.
2.  **Include Context:** Describe the setting, background, and environment if applicable.
3.  **Define the Style:** Mention artistic styles (e.g., "cinematic lighting," "minimalist vector art"), tone (e.g., "professional and formal," "witty and casual"), or coding paradigms (e.g., "a clean, well-documented Python function").
4.  **Add Constraints and Requirements:** Specify negative prompts (what to avoid), desired output format, or key elements that must be included.
5.  **Structure for Clarity:** Use clear language and formatting, such as bullet points or numbered lists, to make the prompt easy to read and use.

Your output should ONLY be the generated prompt itself, without any conversational text like "Here is your prompt:" or "I have generated this for you." Just the prompt text.
`;

const PromptPlaceholder: React.FC = () => (
    <div className="w-full h-full rounded-2xl flex items-center justify-center p-4 text-center text-slate-500 dark:text-slate-400">
        <div>
            <svg className="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <p className="font-semibold">Your generated prompt will appear here</p>
        </div>
    </div>
);

const CopyIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg className={className} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
    </svg>
);

const CheckIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg className={className} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
    </svg>
);


const PromptCenter: React.FC = () => {
    const [description, setDescription] = useState('');
    const [generatedPrompt, setGeneratedPrompt] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isCopied, setIsCopied] = useState(false);
    
    const handleGenerate = async () => {
        if (!description.trim() || isLoading) return;

        setIsLoading(true);
        setGeneratedPrompt(null);
        setError(null);
        setIsCopied(false);

        try {
            const ai = new GoogleGenAI({ apiKey: import.meta.env.VITE_GEMINI_API_KEY || '' });
            const response = await ai.models.generateContent({
                model: 'gemini-2.5-flash',
                contents: description,
                config: {
                    systemInstruction: systemInstruction,
                },
            });
            
            setGeneratedPrompt(response.text ?? null);
        } catch (e) {
            console.error(e);
            setError('Something went wrong while generating the prompt. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };
    
    const handleCopy = () => {
        if (!generatedPrompt) return;
        navigator.clipboard.writeText(generatedPrompt).then(() => {
            setIsCopied(true);
            setTimeout(() => setIsCopied(false), 2000);
        });
    };
    
    return (
        <div>
            <div className="mb-12 text-center">
                <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
                    Dazu <span className="gradient-text">Prompt Center</span>
                </h1>
                <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    Describe your goal, and let our AI craft the perfect, detailed prompt for you to use with any generative AI.
                </p>
            </div>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <div className="bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-lg border border-slate-200/80 dark:border-slate-700/80 flex flex-col order-2 lg:order-1">
                    <h2 className="text-2xl font-bold mb-4 text-slate-800 dark:text-slate-200">1. Describe Your Goal</h2>
                    <p className="text-slate-600 dark:text-slate-400 mb-6">Enter a simple description of what you want to create. The more context you provide, the better the result!</p>
                    
                    <div className="tooltip-container flex-grow">
                        <textarea
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="e.g., A blog post about the benefits of AI in marketing for small businesses."
                            rows={10}
                            className="w-full h-full p-4 rounded-xl bg-slate-50 dark:bg-slate-700 transition input-3d"
                            disabled={isLoading}
                        />
                        <span className="tooltip-text">Provide a simple description or goal. The AI will expand this into a detailed prompt for you.</span>
                    </div>
                    
                    <div className="tooltip-container mt-6">
                        <button
                            onClick={handleGenerate}
                            disabled={isLoading || !description.trim()}
                            className="w-full text-white font-bold px-8 py-4 rounded-xl text-lg hover:opacity-90 transition-opacity duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center shadow-lg"
                            style={{ background: 'var(--gradient)' }}
                        >
                            {isLoading ? (
                                <>
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Generating Prompt...
                                </>
                            ) : '✨ Generate Prompt'}
                        </button>
                        <span className="tooltip-text">Click to generate a detailed prompt based on your description above.</span>
                    </div>
                </div>
                
                <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-lg border border-slate-200/80 dark:border-slate-700/80 flex flex-col items-center justify-center order-1 lg:order-2">
                    <h2 className="text-2xl font-bold mb-4 text-slate-800 dark:text-slate-200 self-start px-2">2. Your Generated Prompt</h2>
                    <div className="w-full h-full min-h-[300px] rounded-2xl overflow-hidden flex flex-col bg-slate-100 dark:bg-slate-900/50 relative input-3d border-none">
                        {isLoading && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center p-4 text-center">
                                <svg className="w-12 h-12 text-slate-400 dark:text-slate-500 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <p className="mt-4 text-slate-600 dark:text-slate-400 animate-fadeIn">Crafting the perfect words...</p>
                            </div>
                        )}
                        {!isLoading && generatedPrompt && (
                             <div className="relative h-full animate-fadeIn">
                                <div className="tooltip-container absolute top-3 right-3">
                                    <button
                                        onClick={handleCopy}
                                        className={`px-3 py-1.5 rounded-lg text-sm font-semibold transition flex items-center gap-2 ${isCopied ? 'bg-green-500 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-300 dark:hover:bg-slate-600'}`}
                                    >
                                        {isCopied ? <CheckIcon className="w-4 h-4"/> : <CopyIcon className="w-4 h-4"/>}
                                        {isCopied ? 'Copied!' : 'Copy'}
                                    </button>
                                    <span className="tooltip-text">Copy the generated prompt to your clipboard.</span>
                                </div>
                                <pre className="p-4 pt-12 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap font-sans h-full overflow-y-auto">
                                    {generatedPrompt}
                                </pre>
                            </div>
                        )}
                        {!isLoading && !generatedPrompt && !error && (
                             <PromptPlaceholder />
                        )}
                        {error && (
                            <div className="text-center text-red-500 p-4 animate-fadeIn flex-grow flex flex-col items-center justify-center">
                                <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <p className="font-semibold">{error}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default PromptCenter;