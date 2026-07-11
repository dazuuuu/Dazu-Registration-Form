import React, { useState, useRef, useEffect } from 'react';
import { GoogleGenAI, Chat } from "@google/genai";

const ChatIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    </svg>
);

const CloseIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
    </svg>
);

const SendIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" className={className} viewBox="0 0 24 24" fill="currentColor">
        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
    </svg>
);

const RobotIcon: React.FC<{ className?: string }> = ({ className }) => (
    <svg viewBox="0 0 40 40" className={className} xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="robot-head-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style={{stopColor: 'var(--gradient-start)'}} />
                <stop offset="100%" style={{stopColor: 'var(--gradient-end)'}} />
            </linearGradient>
        </defs>
        <rect x="5" y="10" width="30" height="25" rx="8" fill="url(#robot-head-gradient)" />
        <circle cx="15" cy="20" r="4" fill="white" />
        <circle cx="25" cy="20" r="4" fill="white" />
        <circle cx="16" cy="21" r="1.5" fill="#1E293B" className="dark:fill-slate-800" />
        <circle cx="26" cy="21" r="1.5" fill="#1E293B" className="dark:fill-slate-800" />
        <line x1="20" y1="10" x2="20" y2="4" stroke="#9CA3AF" strokeWidth="2" />
        <circle cx="20" cy="3" r="3" fill="url(#robot-head-gradient)" />
    </svg>
);


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
You answer questions about Dazu AI Hub using only the following information. Keep answers short and precise.

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

Your main job is to answer questions about Dazu AI Hub. If a user asks about something else, say: "My purpose is to answer questions about Dazu AI Hub. What information do you need about our courses or services?"`;


interface Message {
    sender: 'user' | 'bot';
    text: string;
}

const floatingPrompts = [
    { text: "Courses?", icon: "🎓", position: "bottom-2 right-[4.7rem]" },
    { text: "Pricing?", icon: "💰", position: "bottom-12 right-[4.1rem]" },
    { text: "Services?", icon: "✨", position: "bottom-[5.5rem] right-[1.6rem]" }
];

const Chatbot: React.FC = () => {
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([
        { sender: 'bot', text: "I am Dazu, the guide for Dazu AI Hub. Ask me about our courses, services, or prices." }
    ]);
    const [inputValue, setInputValue] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    
    const chatRef = useRef<Chat | null>(null);
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const chatWindowRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    
    useEffect(() => {
        if (isOpen && !chatRef.current) {
            try {
                const ai = new GoogleGenAI({ apiKey: import.meta.env.VITE_GEMINI_API_KEY || '' });
                chatRef.current = ai.chats.create({
                    model: 'gemini-2.5-flash',
                    config: {
                      systemInstruction: systemInstruction,
                    },
                });
            } catch (error) {
                console.error("Failed to initialize Gemini:", error);
                setMessages(prev => [...prev, { sender: 'bot', text: "Sorry, I'm having trouble connecting right now." }]);
            }
        }
    }, [isOpen]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    useEffect(() => {
        if (isOpen) {
            const chatNode = chatWindowRef.current;
            if (!chatNode) return;

            inputRef.current?.focus();

            const focusableElements = chatNode.querySelectorAll<HTMLElement>(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            const handleKeyDown = (e: KeyboardEvent) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            lastElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            firstElement.focus();
                            e.preventDefault();
                        }
                    }
                } else if (e.key === 'Escape') {
                    setIsOpen(false);
                }
            };
            
            chatNode.addEventListener('keydown', handleKeyDown);

            return () => {
                chatNode.removeEventListener('keydown', handleKeyDown);
                triggerRef.current?.focus();
            };
        }
    }, [isOpen]);

    const handleSendMessage = async () => {
        if (!inputValue.trim() || isLoading) return;

        const userMessage: Message = { sender: 'user', text: inputValue };
        setMessages(prev => [...prev, userMessage]);
        setInputValue('');
        setIsLoading(true);

        try {
            if (!chatRef.current) {
                 throw new Error("Chat not initialized");
            }
            const response = await chatRef.current.sendMessage({ message: inputValue });
            const botMessage: Message = { sender: 'bot', text: response.text ?? "I couldn't generate a response." };
            setMessages(prev => [...prev, botMessage]);
        } catch (error) {
            console.error("Error sending message:", error);
            const errorMessage: Message = { sender: 'bot', text: "Oops! Something went wrong. Please try again." };
            setMessages(prev => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            handleSendMessage();
        }
    };
    
    const handlePromptClick = (promptText: string) => {
        setIsOpen(true);
        // Wait for the transition to finish before focusing and setting value
        setTimeout(() => {
            setInputValue(promptText);
            inputRef.current?.focus();
        }, 300); // Corresponds to the transition duration of the chat window
    };


    return (
        <>
             <div className="fixed bottom-6 right-6 z-50">
                <div className={`relative w-16 h-16 transition-all duration-300 ${isOpen ? 'opacity-0 scale-90 pointer-events-none' : 'opacity-100'}`}>
                    {/* Floating Prompts */}
                    {floatingPrompts.map((prompt, index) => (
                        <button
                            key={prompt.text}
                            onClick={() => handlePromptClick(prompt.text)}
                            className={`animate-fadeInBubble animate-float animate-blinking-glow absolute whitespace-nowrap bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-full shadow-lg text-sm font-semibold flex items-center gap-1.5 hover:scale-105 hover:shadow-xl transition-all ${prompt.position}`}
                            style={{ animationDelay: `${index * 200}ms, ${index * 500}ms, ${index * 300}ms` }}
                            aria-label={`Ask about ${prompt.text}`}
                        >
                            <span>{prompt.icon}</span>
                            {prompt.text}
                        </button>
                    ))}

                    {/* Chat Trigger Button */}
                    <button
                        ref={triggerRef}
                        onClick={() => setIsOpen(!isOpen)}
                        className={`absolute bottom-0 right-0 w-16 h-16 text-white rounded-full shadow-lg flex items-center justify-center transform transition-all duration-300 hover:scale-110 focus:outline-none`}
                        style={{ background: 'var(--gradient)' }}
                        aria-label="Toggle Chatbot"
                        aria-haspopup="dialog"
                        aria-expanded={isOpen}
                        aria-controls="chatbot-window"
                    >
                        <ChatIcon className="w-8 h-8" />
                    </button>
                </div>
            </div>


            <div 
                ref={chatWindowRef}
                id="chatbot-window"
                className={`fixed bottom-6 right-6 w-[calc(100%-3rem)] max-w-sm h-[70vh] max-h-[600px] bg-white dark:bg-gray-800 rounded-3xl shadow-2xl flex flex-col transition-all duration-300 origin-bottom-right z-50 border border-slate-200/80 dark:border-gray-700/80 ${isOpen ? 'opacity-100 scale-100' : 'opacity-0 scale-0 pointer-events-none'}`}
                role={isOpen ? "dialog" : undefined}
                aria-modal={isOpen ? "true" : undefined}
                aria-labelledby="chatbot-header"
            >
                {/* Header */}
                <div className="flex items-center justify-between p-4 text-white rounded-t-3xl" style={{ background: 'var(--gradient)' }}>
                    <h3 id="chatbot-header" className="text-lg font-bold">Chat with Dazu</h3>
                    <button onClick={() => setIsOpen(false)} className="hover:bg-black/10 rounded-full p-1" aria-label="Close chat">
                        <CloseIcon className="w-6 h-6" />
                    </button>
                </div>

                {/* Messages */}
                <div className="flex-1 p-4 overflow-y-auto bg-slate-50 dark:bg-gray-900" aria-live="polite" role="log">
                    <div className="space-y-4">
                        {messages.map((msg, index) => (
                            <div key={index} className={`flex items-end gap-2 ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}>
                                {msg.sender === 'bot' && <RobotIcon className="w-8 h-8 flex-shrink-0" />}
                                 <div className={`max-w-[80%] px-4 py-3 rounded-2xl shadow-sm ${msg.sender === 'user' ? 'bg-slate-800 text-white rounded-br-lg' : 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-bl-lg'}`}>
                                    <p className="text-sm leading-relaxed">{msg.text}</p>
                                </div>
                            </div>
                        ))}
                        {isLoading && (
                            <div className="flex items-end gap-2 justify-start">
                                 <RobotIcon className="w-8 h-8 flex-shrink-0" />
                                <div className="max-w-xs px-4 py-3 rounded-2xl bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded-bl-lg shadow-sm">
                                   <div className="flex items-center space-x-1.5">
                                       <span className="w-2 h-2 bg-slate-400 dark:bg-slate-500 rounded-full animate-pulse [animation-delay:-0.3s]"></span>
                                       <span className="w-2 h-2 bg-slate-400 dark:bg-slate-500 rounded-full animate-pulse [animation-delay:-0.15s]"></span>
                                       <span className="w-2 h-2 bg-slate-400 dark:bg-slate-500 rounded-full animate-pulse"></span>
                                   </div>
                                </div>
                            </div>
                        )}
                    </div>
                    <div ref={messagesEndRef} />
                </div>

                {/* Input */}
                <div className="p-4 border-t border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-b-3xl">
                    <div className="flex items-center space-x-3">
                        <input
                            ref={inputRef}
                            type="text"
                            value={inputValue}
                            onChange={(e) => setInputValue(e.target.value)}
                            onKeyPress={handleKeyPress}
                            placeholder="Ask a question..."
                            aria-label="Type your message here"
                            className="flex-1 px-4 py-2.5 rounded-full input-3d bg-slate-100 dark:bg-slate-700"
                            disabled={isLoading}
                        />
                        <button 
                          onClick={handleSendMessage} 
                          className="text-white rounded-full p-3 hover:opacity-90 disabled:opacity-50 transition-opacity" 
                          style={{ background: 'var(--gradient)' }}
                          disabled={isLoading || !inputValue.trim()} 
                          aria-label="Send message">
                            <SendIcon className="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default Chatbot;