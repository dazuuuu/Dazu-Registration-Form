import React, { useState, useEffect, useRef } from 'react';
import { GoogleGenAI } from "@google/genai";

const systemInstruction = `You are Dazu, the AI guide for Dazu AI Hub. Follow these rules for all your responses:

**Writing Style Rules:**
* Use clear, simple language.
* Be spartan and informative.
* Use short, impactful sentences.
* Use active voice.
* Focus on practical, actionable insights.
* Address the reader directly using "you" and "your".

**Formatting Rules:**
* Do not use em dashes (—). Use standard punctuation like periods or commas.
* Do not use semicolons.
* Do not use markdown or asterisks.
* Do not use hashtags.

**Words to Avoid:**
* Do not use: can, may, just, that, very, really, literally, actually, certainly, probably, basically, could, maybe, delve, embark, enlightening, esteemed, shed light, craft, crafting, imagine, realm, game-changer, unlock, discover, skyrocket, abyss, not alone, in a world where, revolutionize, disruptive, utilize, utilizing, dive deep, tapestry, illuminate, unveil, pivotal, intricate, elucidate, hence, furthermore, realm, however, harness, exciting, groundbreaking, cutting-edge, remarkable, it, remains true.

**Things to Avoid:**
* Avoid constructions like "...not just this, but also this".
* Avoid metaphors and clichés.
* Avoid generalizations.
* Avoid setup language like "in conclusion".
* Avoid output warnings or notes.
* Avoid unnecessary adjectives and adverbs.

**Your Knowledge Base:**
You answer questions about Dazu AI Hub using only the following information. Keep answers short and precise. Your goal is to encourage people to sign up for a course or ask about our services.

Courses & Pricing: We offer courses from KES 8,000 to KES 30,000. The AI Masterclass is KES 10,000. Advanced AI for Your Profession is KES 15,000.

Services: We build modern websites and apps starting from KES 20,000. We also offer custom AI automations and consulting.

Achievements: We trained over 600 people. We delivered over 50 projects.

Payment Methods: You can pay via M-Pesa. Till is 6166297. Paybill is 4167991 with your name as the account.

Contact & Registration: Our office is at Thika CBS, 1st floor room 4. Call 0750116600 or email dazuai01@gmail.com. Use the "Register Now" or "Get Started" buttons to sign up.

Opening Hours: We are open Monday to Saturday, from 9 AM to 7 PM.

**Creative Training Keywords:**
Use the following keywords to make your answers about courses and services more informative.
- Courses: AI Masterclass, machine learning, neural networks, AI for Business, branding, marketing, Advanced AI, healthcare, finance, engineering, Automation, AI agents, Prompt Engineering, Generative AI, creatives, artists, designers, writers, AI Ethics, responsible innovation, Web development, frontend, backend, AI integration.
- Services: Website development, app development, AI-integrated, custom automations, streamline workflows, AI consulting, strategic guidance, AI content creation, data analysis, business insights, custom chatbot development, conversational AI.

If a user asks about something else, say: "My purpose is to answer questions about Dazu AI Hub. What information do you need about our courses or services?"`;

const SendIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
    </svg>
);


const DazuLive: React.FC = () => {
    const [userInput, setUserInput] = useState('');
    const [aiResponse, setAiResponse] = useState("I am Dazu, the AI mascot for Dazu AI Hub. Ask a question about our courses or services.");
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const aiRef = useRef<GoogleGenAI | null>(null);

    useEffect(() => {
        try {
            aiRef.current = new GoogleGenAI({ apiKey: import.meta.env.VITE_GEMINI_API_KEY || '' });
        } catch (err) {
            console.error("Failed to initialize Gemini:", err);
            setError("Oops! I'm having a little trouble getting started. Please try refreshing the page.");
            setAiResponse(''); // Clear the default message on init failure
        }
    }, []);


    const handleAskDazu = async () => {
        if (!userInput.trim() || isLoading) return;
        
        if (!aiRef.current) {
            setError("AI assistant is not available. Please refresh the page.");
            return;
        }

        setIsLoading(true);
        setError(null);
        setAiResponse('');

        try {
            const response = await aiRef.current.models.generateContent({
                model: 'gemini-2.5-flash',
                contents: userInput,
                config: {
                    systemInstruction: systemInstruction,
                },
            });
            setAiResponse(response.text ?? '');
        } catch (err) {
            console.error("Error fetching AI response:", err);
            setError("Oops! I'm having a little trouble thinking right now. Please try again in a moment.");
        } finally {
            setIsLoading(false);
            setUserInput('');
        }
    };
    
    const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            handleAskDazu();
        }
    };


    return (
        <div>
            <div className="text-center mb-12">
                <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
                    Meet <span className="gradient-text">Dazu</span>
                </h1>
                <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    I'm the official AI mascot for Dazu AI Hub. I'm live and ready to answer your questions!
                </p>
            </div>

            <div className="max-w-4xl mx-auto bg-white dark:bg-slate-800 rounded-3xl shadow-lg border border-slate-200/80 dark:border-slate-700/80 p-8 grid md:grid-cols-2 gap-8 items-center">
                {/* Robot Visual */}
                <div className="flex flex-col items-center justify-center" aria-hidden="true">
                    <div className="relative">
                        <svg width="250" height="250" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="robot-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style={{stopColor: 'var(--gradient-start)'}} />
                                    <stop offset="100%" style={{stopColor: 'var(--gradient-end)'}} />
                                </linearGradient>
                            </defs>
                            <g className="transition-transform duration-500 ease-in-out" style={{transform: isLoading ? 'translateY(-10px)' : 'translateY(0)'}}>
                                {/* Body */}
                                <rect x="50" y="90" width="100" height="80" rx="20" fill="url(#robot-gradient)" />
                                <rect x="60" y="100" width="80" height="60" rx="10" fill="#F0FDF4" className="dark:fill-slate-700" />
                                <circle cx="100" cy="130" r="15" fill="url(#robot-gradient)" className={isLoading ? 'animate-pulse' : ''} />

                                {/* Head */}
                                <rect x="65" y="40" width="70" height="60" rx="15" fill="url(#robot-gradient)" />
                                
                                {/* Eyes */}
                                <g>
                                    <circle cx="85" cy="70" r="8" fill="white" />
                                    <circle cx="115" cy="70" r="8" fill="white" />
                                    <circle cx="87" cy="72" r="3" fill="#1E293B" className="transition-transform duration-300" />
                                    <circle cx="117" cy="72" r="3" fill="#1E293B" className="transition-transform duration-300" />
                                </g>
                                
                                {/* Antenna */}
                                <line x1="100" y1="40" x2="100" y2="20" stroke="#9ca3af" strokeWidth="4" />
                                <circle cx="100" cy="15" r="7" fill="url(#robot-gradient)" className="animate-pulse" />
                            </g>
                        </svg>
                    </div>
                </div>

                {/* Chat Interface */}
                <div className="flex flex-col h-full">
                    <div className="bg-slate-100 dark:bg-slate-700 rounded-xl p-6 flex-grow min-h-[150px] flex items-center justify-center relative shadow-inner" role="status" aria-live="polite">
                        <div aria-hidden="true" className="absolute top-[-10px] left-8 w-0 h-0 border-l-[10px] border-l-transparent border-b-[15px] border-b-slate-100 dark:border-b-slate-700 border-r-[10px] border-r-transparent"></div>
                        {isLoading ? (
                                <div className="flex items-center space-x-2">
                                <span className="w-3 h-3 bg-teal-400 rounded-full animate-pulse [animation-delay:-0.3s]"></span>
                                <span className="w-3 h-3 bg-teal-400 rounded-full animate-pulse [animation-delay:-0.15s]"></span>
                                <span className="w-3 h-3 bg-teal-400 rounded-full animate-pulse"></span>
                            </div>
                        ) : (
                            <p className="text-slate-800 dark:text-slate-200 text-center text-lg italic leading-relaxed">
                                {error ? error : `"${aiResponse}"`}
                            </p>
                        )}
                    </div>
                    <div className="mt-6 flex items-center space-x-3">
                            <input
                            type="text"
                            value={userInput}
                            onChange={(e) => setUserInput(e.target.value)}
                            onKeyPress={handleKeyPress}
                            placeholder="Ask Dazu a question..."
                            aria-label="Ask Dazu a question"
                            className="flex-1 px-4 py-3 rounded-full input-3d bg-slate-50 dark:bg-slate-700"
                            disabled={isLoading}
                        />
                        <button 
                            onClick={handleAskDazu} 
                            className="text-white rounded-full p-3.5 hover:opacity-90 disabled:opacity-50 transition-opacity" 
                            style={{ background: 'var(--gradient)' }}
                            disabled={isLoading || !userInput.trim()} 
                            aria-label="Send message">
                            <SendIcon className="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DazuLive;