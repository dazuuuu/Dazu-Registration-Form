import React from 'react';

interface ServiceCardItem {
    title: string;
    description: string;
    image: string;
    priceInfo?: string;
}

const services: ServiceCardItem[] = [
    {
        title: 'Modern Websites and Apps Development',
        description: 'We build fast, responsive, and AI-integrated websites and apps that captivate your audience and drive growth.',
        image: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1472&auto=format&fit=crop',
        priceInfo: "Starts from KES 20,000"
    },
    {
        title: 'Custom Automations',
        description: 'Streamline your workflows and boost productivity with bespoke AI-powered automation solutions.',
        image: 'https://images.unsplash.com/photo-1593349480503-68551a1c9929?q=80&w=1470&auto=format&fit=crop',
        priceInfo: "Contact for Quote"
    },
    {
        title: 'Expert AI Consulting',
        description: 'Get strategic guidance on implementing AI in your business to gain a competitive edge and innovate.',
        image: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=1470&auto=format&fit=crop',
        priceInfo: "Contact for Quote"
    },
    {
        title: 'AI Agents & Workflows',
        description: 'Design and deploy intelligent AI agents to automate business processes and create efficient custom workflows.',
        image: 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=1470&auto=format&fit=crop',
        priceInfo: "Contact for Quote"
    },
    {
        title: 'AI Content Creation',
        description: 'Generate high-quality, engaging content for your blog, social media, and marketing campaigns using advanced AI.',
        image: 'https://images.unsplash.com/photo-1587614295999-6c1c13675123?q=80&w=1374&auto=format&fit=crop',
        priceInfo: "Starts from KES 15,000/mo"
    },
    {
        title: 'Data Analysis & Insights',
        description: 'Unlock the power of your data. We use AI to analyze complex datasets and provide actionable business insights.',
        image: 'https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?q=80&w=1470&auto=format&fit=crop',
        priceInfo: "Contact for Quote"
    },
    {
        title: 'Custom Chatbot Development',
        description: 'Engage your customers 24/7 with a smart, conversational AI chatbot tailored to your business needs.',
        image: 'https://images.unsplash.com/photo-1555255707-c07969078a5b?q=80&w=1470&auto=format&fit=crop',
        priceInfo: "Starts from KES 25,000"
    }
];

const ServiceCard: React.FC<{ item: ServiceCardItem }> = ({ item }) => (
    <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-sm hover:shadow-xl transition-shadow duration-300 border border-slate-200/80 dark:border-slate-700/80 flex flex-col overflow-hidden card-hover-effect">
        <div className="w-full h-48 overflow-hidden">
             <img src={item.image} alt={`An illustrative image for our ${item.title} service`} className="w-full h-full object-cover" />
        </div>
        <div className="p-6 flex flex-col flex-grow">
            <h3 className="text-xl font-bold text-slate-900 dark:text-slate-100 mb-3">{item.title}</h3>
            <p className="text-slate-600 dark:text-slate-400 leading-relaxed flex-grow text-base">{item.description}</p>
            {item.priceInfo && (
                <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <div className="flex justify-end items-center">
                        <p className="text-sm font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-3 py-1.5 rounded-full">{item.priceInfo}</p>
                    </div>
                </div>
            )}
        </div>
    </div>
);

const Services: React.FC = () => {
    return (
        <div>
            <div className="mb-12 text-center">
                 <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
                    Our <span className="gradient-text">AI-Powered</span> Services
                </h1>
                <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-3xl mx-auto">
                   From intelligent websites to custom automations, we provide a suite of services to elevate your business.
                </p>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-8">
                {services.map((service) => (
                    <ServiceCard key={service.title} item={service} />
                ))}
            </div>
        </div>
    );
};

export default Services;