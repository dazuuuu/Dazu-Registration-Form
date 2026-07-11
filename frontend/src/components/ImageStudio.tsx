import React, { useState, useEffect, useRef } from 'react';
import { GoogleGenAI } from "@google/genai";

const ImagePlaceholder: React.FC = () => (
    <div className="w-full aspect-square rounded-2xl flex items-center justify-center image-placeholder-3d">
        <svg className="w-24 h-24 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
    </div>
);

const loadingSteps = [
    'Initializing the AI art engine... 🎨',
    'Gathering inspiration from the cosmos... ✨',
    'Mixing digital paints and pixels... 🖌️',
    'Sketching the initial concept... ✏️',
    'Rendering the final masterpiece... 🖼️',
    'Almost there, adding the final sparkle... 🌟'
];

export const ImageStudio: React.FC = () => {
    const [prompt, setPrompt] = useState('');
    const [generatedImage, setGeneratedImage] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loadingMessage, setLoadingMessage] = useState('');
    
    const intervalRef = useRef<number | null>(null);

    useEffect(() => {
        if (isLoading) {
            let step = 0;
            setLoadingMessage(loadingSteps[step]);
            intervalRef.current = window.setInterval(() => {
                step = (step + 1) % loadingSteps.length;
                setLoadingMessage(loadingSteps[step]);
            }, 2500);
        } else {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        }
        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
            }
        };
    }, [isLoading]);
    
    const handleGenerate = async () => {
        if (!prompt.trim() || isLoading) return;

        setIsLoading(true);
        setGeneratedImage(null);
        setError(null);

        try {
            const ai = new GoogleGenAI({ apiKey: import.meta.env.VITE_GEMINI_API_KEY || '' });
            const response = await ai.models.generateImages({
                model: 'imagen-4.0-generate-001',
                prompt: prompt,
                config: {
                    numberOfImages: 1,
                    outputMimeType: 'image/jpeg',
                    aspectRatio: '1:1',
                },
            });
            
            const base64ImageBytes = response.generatedImages?.[0]?.image?.imageBytes;
            if (!base64ImageBytes) throw new Error('No image returned');
            const imageUrl = `data:image/jpeg;base64,${base64ImageBytes}`;
            setGeneratedImage(imageUrl);

        } catch (e) {
            console.error(e);
            setError('Something went wrong while generating the image. Please try again.');
        } finally {
            setIsLoading(false);
        }
    };
    
    return (
        <div>
            <div className="mb-12 text-center">
                <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
                    Dazu <span className="gradient-text">Image Studio</span>
                </h1>
                <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    Bring your creative ideas to life. Describe anything you can imagine, and let our AI create it for you.
                </p>
            </div>
            
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <div className="bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-lg border border-slate-200/80 dark:border-slate-700/80 flex flex-col order-2 lg:order-1">
                    <h2 className="text-2xl font-bold mb-4 text-slate-800 dark:text-slate-200">Describe Your Image</h2>
                    <p className="text-slate-600 dark:text-slate-400 mb-6">Be as detailed as you like. The more descriptive you are, the better the result!</p>
                    
                    <textarea
                        value={prompt}
                        onChange={(e) => setPrompt(e.target.value)}
                        placeholder="e.g., A futuristic cyberpunk cityscape at night, with neon signs reflecting on wet streets, cinematic lighting"
                        rows={5}
                        className="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-700 transition flex-grow input-3d"
                        disabled={isLoading}
                    />
                    
                    <button
                        onClick={handleGenerate}
                        disabled={isLoading || !prompt.trim()}
                        className="w-full text-white font-bold px-8 py-4 rounded-xl text-lg hover:opacity-90 transition-opacity duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center shadow-lg mt-6"
                        style={{ background: 'var(--gradient)' }}
                    >
                        {isLoading ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Generating...
                            </>
                        ) : 'Generate Image'}
                    </button>
                </div>
                
                <div className="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-lg border border-slate-200/80 dark:border-slate-700/80 flex flex-col items-center justify-center order-1 lg:order-2">
                    <div className="w-full aspect-square rounded-2xl overflow-hidden flex items-center justify-center bg-slate-100 dark:bg-slate-900/50 relative image-placeholder-3d border-none">
                        {isLoading && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center p-4 text-center">
                                <ImagePlaceholder />
                                <p className="mt-4 text-slate-600 dark:text-slate-400 animate-fadeIn">{loadingMessage}</p>
                            </div>
                        )}
                        {!isLoading && generatedImage && (
                            <img src={generatedImage} alt={prompt} className="w-full h-full object-contain animate-fadeIn" />
                        )}
                        {!isLoading && !generatedImage && !error && (
                             <div className="text-center text-slate-500 dark:text-slate-400 p-4">
                                <svg className="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <p className="font-semibold">Your generated image will appear here</p>
                             </div>
                        )}
                        {error && (
                            <div className="text-center text-red-500 p-4 animate-fadeIn">
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
