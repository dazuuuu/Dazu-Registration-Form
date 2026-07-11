import { 
  Code2, 
  ShieldCheck, 
  ChartColumn, 
  Brain, 
  Cpu, 
  Globe, 
  Palette, 
  Video, 
  Sparkles, 
  Briefcase, 
  GraduationCap, 
  Network, 
  Bot, 
  Megaphone,
  Terminal,
  Share2,
  type LucideIcon
} from 'lucide-react';

export interface Course {
  id: string;
  title: string;
  description: string;
  icon: LucideIcon;
  duration: string;
  level: 'Beginner' | 'Intermediate' | 'Advanced';
  category: 'Software & Web' | 'AI & Data Science' | 'Security & Infrastructure' | 'Creative & Marketing';
  price: string;
  rating: number;
  ratingCount: number;
  image: string;
  syllabus: string[];
}

export const coursesData: Course[] = [
  {
    id: 'python',
    title: 'Python',
    description: 'Master general-purpose Python programming, object-oriented concepts, and automation scripting.',
    icon: Terminal,
    duration: '3 Months',
    level: 'Beginner',
    category: 'Software & Web',
    price: 'KES 60,000',
    rating: 4.9,
    ratingCount: 158,
    image: 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Python Basics, Variables & Data Types',
      'Control Flows, Loops & Functions',
      'Object-Oriented Programming (OOP) in Python',
      'Working with Databases & File I/O',
      'Web Scraping & API Integrations with Requests',
      'Building Automation Scripts & CLI Tools'
    ]
  },
  {
    id: 'web-dev',
    title: 'Web development',
    description: 'Create fast, responsive, and SEO-friendly websites using standard modern frameworks.',
    icon: Globe,
    duration: '6 Months',
    level: 'Beginner',
    category: 'Software & Web',
    price: 'KES 120,000',
    rating: 4.8,
    ratingCount: 142,
    image: 'https://images.unsplash.com/photo-1547658719-da2b51169166?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Web Design Principles & UI Layouts',
      'HTML5 Semantic Tags & CSS Layouts (Flexbox/Grid)',
      'Responsive Web Design with Tailwind CSS',
      'Interactivity with JavaScript',
      'SEO Best Practices & Web Performance Tuning',
      'Hosting, Domains, and Deploying Static Sites'
    ]
  },
  {
    id: 'software-dev',
    title: 'Software Development',
    description: 'Frontend, Backend and Full Stack Development using modern technologies.',
    icon: Code2,
    duration: '6 Months',
    level: 'Beginner',
    category: 'Software & Web',
    price: 'KES 120,000',
    rating: 4.9,
    ratingCount: 186,
    image: 'https://images.unsplash.com/photo-1605379399642-870262d3d051?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'HTML5, CSS3, Modern JavaScript (ES6+)',
      'React.js and Tailwind CSS Frontends',
      'Node.js, Express, and RESTful APIs',
      'Relational and NoSQL Databases (PostgreSQL, MongoDB)',
      'Git, GitHub, and Cloud Deployment (Vercel, Render)',
      'Capstone Projects & Technical Interview Prep'
    ]
  },
  {
    id: 'machine-learning',
    title: 'Machine learning',
    description: 'Build and deploy intelligent predictive models from scratch using modern frameworks.',
    icon: Cpu,
    duration: '6 Months',
    level: 'Advanced',
    category: 'AI & Data Science',
    price: 'KES 120,000',
    rating: 4.8,
    ratingCount: 94,
    image: 'https://images.unsplash.com/photo-1527474305487-b87b222841cc?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Python for Machine Learning (Scikit-Learn, SciPy)',
      'Supervised Learning (Regression, Classification)',
      'Unsupervised Learning (Clustering, Dimensionality)',
      'Neural Networks & Deep Learning Basics',
      'Model Evaluation, Hyperparameter Tuning',
      'Deploying ML Models as Microservices'
    ]
  },
  {
    id: 'data-analytics',
    title: 'Data analytics',
    description: 'Analyze complex data, build beautiful dashboards, and drive business intelligence.',
    icon: ChartColumn,
    duration: '6 Months',
    level: 'Beginner',
    category: 'AI & Data Science',
    price: 'KES 120,000',
    rating: 4.7,
    ratingCount: 115,
    image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Data Manipulation using MS Excel & Google Sheets',
      'SQL Databases (Structuring & Querying Data)',
      'Data Visualization with Tableau & Power BI',
      'Introduction to Python for Data Science (Pandas, NumPy)',
      'Statistical Analysis & Decision Making',
      'Building Interactive Business Dashboards'
    ]
  },
  {
    id: 'generative-ai',
    title: 'Generative Ai',
    description: 'Create premium copy, imagery, and software code using modern generative tools.',
    icon: Sparkles,
    duration: '6 Months',
    level: 'Beginner',
    category: 'AI & Data Science',
    price: 'KES 60,000',
    rating: 4.9,
    ratingCount: 210,
    image: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Introduction to Generative AI & Prompt Design',
      'Text Generation & Marketing Copy (ChatGPT, Gemini)',
      'AI Image Generation (Midjourney, DALL-E, Stable Diffusion)',
      'Coding Assistance with Github Copilot & cursor',
      'Creating High-Converting Ad Copy & Assets',
      'Productivity Workflows & Automations'
    ]
  },
  {
    id: 'ai-for-business',
    title: 'Ai for business',
    description: 'Implement secure, highly effective AI solutions to skyrocket business growth.',
    icon: Briefcase,
    duration: '3 Months',
    level: 'Intermediate',
    category: 'Creative & Marketing',
    price: 'KES 60,000',
    rating: 4.6,
    ratingCount: 73,
    image: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'AI Landscape in Enterprise & Small Business',
      'Business Process Mapping & AI Feasibility',
      'Integrating AI Chatbots for Customer Support',
      'Automated Content Creation & Ad Optimization',
      'Security, Privacy, and Intellectual Property',
      'ROI Calculations & AI Culture Adoption'
    ]
  },
  {
    id: 'ai-digital-marketing',
    title: 'Ai & digital marketing',
    description: 'Master content creation, ad targeting, and digital marketing powered by AI.',
    icon: Megaphone,
    duration: '6 Months',
    level: 'Beginner',
    category: 'Creative & Marketing',
    price: 'KES 60,000',
    rating: 4.7,
    ratingCount: 88,
    image: 'https://images.unsplash.com/photo-1432821596592-e2c18b78144f?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'AI in Modern Digital Marketing Channels',
      'AI Copywriting & Ad Creative Generation',
      'SEO and Keyword Research with AI Assistance',
      'Social Media Algorithm Strategies & Calendar Planning',
      'Meta & Google Paid Ad Campaigns Optimization',
      'Marketing Analytics & AI-Driven Insights'
    ]
  },
  {
    id: 'graphics-design',
    title: 'Graphics & design',
    description: 'Master branding, UI design, color theory, and professional digital graphics.',
    icon: Palette,
    duration: '6 Months',
    level: 'Beginner',
    category: 'Creative & Marketing',
    price: 'KES 40,000',
    rating: 4.8,
    ratingCount: 105,
    image: 'https://images.unsplash.com/photo-1561070791-26c113006238?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Principles of Graphic Design & Typography',
      'Adobe Photoshop (Photo Editing & Manipulation)',
      'Adobe Illustrator (Vector Art & Logo Design)',
      'UI/UX Design Fundamentals with Figma',
      'Corporate Branding & Visual Identity',
      'Portfolio Development & Freelancing Guide'
    ]
  },
  {
    id: 'networking',
    title: 'Networking',
    description: 'Build, secure, and maintain computer networks and server infrastructures.',
    icon: Network,
    duration: '3 Months',
    level: 'Intermediate',
    category: 'Security & Infrastructure',
    price: 'KES 60,000',
    rating: 4.6,
    ratingCount: 62,
    image: 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Network Fundamentals & OSI/TCP-IP Models',
      'Subnetting, Routing Protocols, & Switching',
      'Network Services (DHCP, DNS, Active Directory)',
      'Wireless Networking & Basic Firewalls',
      'Virtualization (Hyper-V, VirtualBox, Docker)',
      'CompTIA Network+ Certification Prep'
    ]
  },
  {
    id: 'ai-automation',
    title: 'Ai automation',
    description: 'Automate repetitive workflows and connect platforms using AI pipelines.',
    icon: Bot,
    duration: '6 Weeks',
    level: 'Beginner',
    category: 'Software & Web',
    price: 'KES 45,000',
    rating: 4.8,
    ratingCount: 54,
    image: 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Workflow Automation Concepts (triggers, actions)',
      'Using Make.com & Zapier to connect apps',
      'Writing Python/JS connectors for API integrations',
      'Automating Email/Slack tasks with AI agents',
      'Extracting and parsing unstructured documents',
      'Testing, Error-handling, and scheduling cron loops'
    ]
  },
  {
    id: 'social-media-management',
    title: 'Social media management',
    description: 'Learn content strategy, community engagement, brand voice, and digital ad campaigns.',
    icon: Share2,
    duration: '1 Month',
    level: 'Beginner',
    category: 'Creative & Marketing',
    price: 'KES 20,000',
    rating: 4.7,
    ratingCount: 45,
    image: 'https://images.unsplash.com/photo-1611162617213-7d7a39e9b1d7?auto=format&fit=crop&w=600&q=80',
    syllabus: [
      'Social Media Strategy & Platform Demographics',
      'Content Creation & Editorial Calendars',
      'Community Management & Engagement Tactics',
      'Analytics, KPIs, and Growth Reporting',
      'Paid Social Campaigns & Audience Targeting',
      'Influencer Marketing & Brand Partnerships'
    ]
  }
];
