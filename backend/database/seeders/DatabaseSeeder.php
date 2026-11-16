<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Client;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\Page;
use App\Models\Post;
use App\Models\ProcessStep;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Stat;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo content so the site looks complete right after install.
 * Everything here can be edited/deleted from the admin panel.
 */
class DatabaseSeeder extends Seeder
{
    private function u(string $id, int $w = 1600): string
    {
        return "https://images.unsplash.com/{$id}?auto=format&fit=crop&w={$w}&q=80";
    }

    public function run(): void
    {
        User::firstOrCreate(
            ['email' => config('site.admin_email')],
            ['name' => 'Administrator', 'password' => config('site.admin_password')]
        );

        $img = [
            'living' => 'photo-1618221195710-dd6b41faaea6',
            'living2' => 'photo-1600210492486-724fe5c67fb0',
            'house' => 'photo-1600585154340-be6161a56a0c',
            'sofa' => 'photo-1586023492125-27b2c045efd7',
            'bed' => 'photo-1616594039964-ae9021a400a0',
            'kitchen' => 'photo-1556909114-f6e7ad7d3136',
            'office' => 'photo-1497366216548-37526070297c',
            'office2' => 'photo-1497366811353-6870744d04b2',
            'dining' => 'photo-1617806118233-18e1de247200',
            'restaurant' => 'photo-1517248135467-4c7edcad34c4',
            'room' => 'photo-1505693416388-ac5ce068fe85',
            'interior' => 'photo-1600607687939-ce8a6c25118c',
        ];

        /* ---------------- Settings ---------------- */
        $settings = [
            'site_name' => 'Loon Interior',
            'tagline' => 'Design · Build · Live Beautifully',
            'primary_color' => '#b08d57',
            'footer_about' => 'We design and build timeless residential and commercial interiors across Bangladesh — from concept and 3D visualization to on-time handover.',
            'copyright' => '© '.date('Y').' Loon Interior. All rights reserved.',
            'phone' => '+880 1700-000000',
            'phone_2' => '+880 1800-000000',
            'whatsapp' => '+8801700000000',
            'email' => 'info@looninterior.com',
            'address' => 'House 00, Road 00, Gulshan-1, Dhaka 1212, Bangladesh',
            'office_hours' => 'Sat – Thu: 10:00 AM – 7:00 PM',
            'map_embed' => 'https://www.google.com/maps?q=Gulshan%201%20Dhaka&output=embed',
            'facebook' => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'youtube' => 'https://youtube.com/',
            'linkedin' => 'https://linkedin.com/',
            'tiktok' => '',
            'pinterest' => '',
            'messenger' => '',
            'home_about_tag' => 'About Us',
            'home_about_title' => 'Crafting Spaces That Reflect Who You Are',
            'home_about_text' => "For over a decade we have helped families and businesses turn empty spaces into inspiring homes, offices and showrooms. Our in-house designers, engineers and craftsmen manage everything — concept, 3D design, fabrication and installation — so you get one accountable team and a fixed, transparent budget.",
            'home_about_image' => $this->u($img['living2'], 1000),
            'home_about_points' => "Free consultation & site visit\nPhotorealistic 3D design before work starts\nFixed BOQ – no hidden cost\nOn-time handover with warranty",
            'years_experience' => '12',
            'services_title' => 'Our Services',
            'services_subtitle' => 'End-to-end interior solutions for every kind of space.',
            'projects_title' => 'Featured Projects',
            'projects_subtitle' => 'A glimpse of spaces we have designed and delivered.',
            'features_title' => 'Why Choose Us',
            'features_subtitle' => 'What makes our clients come back and refer us.',
            'process_title' => 'How We Work',
            'process_subtitle' => 'A clear, step-by-step process from first call to handover.',
            'testimonials_title' => 'What Our Clients Say',
            'clients_title' => 'Trusted By',
            'faq_title' => 'Frequently Asked Questions',
            'blog_title' => 'Latest Insights',
            'quote_title' => 'Get a Free Quote',
            'quote_text' => 'Tell us about your space — our consultant will call you within 24 hours.',
            'cta_title' => 'Ready to Transform Your Space?',
            'cta_text' => 'Book a free consultation and get a 3D concept for your home or office.',
            'cta_button' => 'Book Free Consultation',
            'cta_bg' => $this->u($img['interior']),
            'page_header_bg' => $this->u($img['living']),
            'about_title' => 'Designing Interiors With Purpose Since 2014',
            'about_content' => '<p>We are a full-service interior design and build firm based in Dhaka. Our multidisciplinary team of interior designers, architects, engineers and skilled craftsmen work together under one roof to deliver spaces that are beautiful, functional and built to last.</p><p>Every project begins with listening. We study how you live or work, understand your budget, and translate it into a design you can see in 3D before a single nail is hammered. Our own factory ensures quality control of every piece of furniture and fixture we deliver.</p>',
            'about_image' => $this->u($img['sofa'], 1000),
            'mission' => 'To deliver thoughtfully designed, high-quality interiors on time and on budget, with complete transparency.',
            'vision' => 'To be the most trusted interior design brand in Bangladesh, known for craftsmanship and client happiness.',
            'meta_title' => 'Loon Interior | Interior Design & Build Company in Bangladesh',
            'meta_description' => 'Residential & commercial interior design, 3D visualization, turnkey fit-out and custom furniture in Dhaka, Bangladesh.',
            'meta_keywords' => 'interior design bangladesh, interior design dhaka, office interior, home interior, apartment interior',
            'logo' => 'brand/loon-logo.svg',
            'logo_light' => 'brand/loon-logo-light.svg',
            'favicon' => 'brand/loon-icon.svg',
            'og_image' => '',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        /* ---------------- Sliders ---------------- */
        $sliders = [
            ['Luxury Interiors, Crafted For You', 'Residential & commercial interior design and turnkey execution across Bangladesh.', $img['living']],
            ['Workspaces That Inspire', 'Modern office interiors designed for productivity and brand identity.', $img['office']],
            ['From 3D Concept to Handover', 'See your space in photorealistic 3D before we build it — on time, on budget.', $img['house']],
        ];
        foreach ($sliders as $i => [$title, $sub, $image]) {
            Slider::create([
                'title' => $title, 'subtitle' => $sub, 'image' => $this->u($image),
                'button_text' => 'Get a Free Quote', 'button_link' => '/get-quote', 'sort_order' => $i,
            ]);
        }

        /* ---------------- Services ---------------- */
        $services = [
            ['Residential Interior', 'home', 'Complete apartment, duplex and villa interiors tailored to your lifestyle.', $img['living2']],
            ['Commercial Interior', 'briefcase', 'Showrooms, retail outlets and hospitality spaces that attract customers.', $img['restaurant']],
            ['Office Interior', 'monitor', 'Functional, brand-focused corporate offices and co-working spaces.', $img['office2']],
            ['3D Design & Visualization', 'layers', 'Photorealistic 3D renders and walkthroughs before construction starts.', $img['interior']],
            ['Kitchen & Wardrobe', 'grid', 'Modular kitchens and wardrobes built in our own factory.', $img['kitchen']],
            ['Custom Furniture', 'box', 'Bespoke furniture crafted from premium wood and imported materials.', $img['sofa']],
        ];
        foreach ($services as $i => [$title, $icon, $short, $image]) {
            Service::create([
                'title' => $title, 'slug' => Str::slug($title), 'icon' => $icon,
                'short_description' => $short, 'image' => $this->u($image, 1200), 'is_featured' => true, 'sort_order' => $i,
                'description' => "<p>{$short}</p><h3>What's included</h3><ul><li>Site visit and measurement</li><li>Concept & space planning</li><li>3D design with revisions</li><li>Detailed BOQ with fixed pricing</li><li>Fabrication, installation and handover</li></ul><p>Our team manages every step so you can enjoy a stress-free experience.</p>",
            ]);
        }

        /* ---------------- Categories & Projects ---------------- */
        $cats = [];
        foreach (['Residential', 'Commercial', 'Office', 'Restaurant', 'Showroom'] as $i => $name) {
            $cats[$name] = ProjectCategory::create(['name' => $name, 'slug' => Str::slug($name), 'sort_order' => $i])->id;
        }

        $projects = [
            ['Modern Apartment at Gulshan', 'Residential', 'Gulshan, Dhaka', '2,400 sqft', $img['living'], [$img['bed'], $img['dining'], $img['kitchen']]],
            ['Duplex Residence at Bashundhara', 'Residential', 'Bashundhara R/A, Dhaka', '4,200 sqft', $img['house'], [$img['living2'], $img['room']]],
            ['Corporate Office at Banani', 'Office', 'Banani, Dhaka', '6,000 sqft', $img['office'], [$img['office2']]],
            ['Tech Startup Workspace', 'Office', 'Mohakhali DOHS, Dhaka', '3,500 sqft', $img['office2'], [$img['office']]],
            ['Fine Dining Restaurant', 'Restaurant', 'Dhanmondi, Dhaka', '2,800 sqft', $img['restaurant'], [$img['dining']]],
            ['Lifestyle Showroom', 'Showroom', 'Uttara, Dhaka', '1,800 sqft', $img['interior'], [$img['sofa']]],
            ['Minimal Family Home', 'Residential', 'Mirpur DOHS, Dhaka', '1,650 sqft', $img['living2'], [$img['bed'], $img['kitchen']]],
            ['Boutique Retail Store', 'Commercial', 'Chattogram', '1,200 sqft', $img['sofa'], [$img['interior']]],
        ];
        foreach ($projects as $i => [$title, $cat, $loc, $area, $cover, $gallery]) {
            $p = Project::create([
                'project_category_id' => $cats[$cat], 'title' => $title, 'slug' => Str::slug($title),
                'client_name' => 'Private Client', 'location' => $loc, 'area' => $area, 'year' => (string) (date('Y') - ($i % 3)),
                'short_description' => 'A complete design and build project focused on comfort, light and timeless materials.',
                'description' => '<p>The client wanted a warm, elegant space that felt open and functional. We reworked the layout, introduced a neutral palette with natural wood and brass accents, and designed custom storage throughout.</p><p>The project was completed within the agreed timeline with full 3D approval before execution.</p>',
                'cover_image' => $this->u($cover, 1400), 'is_featured' => $i < 6, 'sort_order' => $i,
            ]);
            foreach (array_merge([$cover], $gallery) as $j => $g) {
                $p->images()->create(['image' => $this->u($g, 1400), 'sort_order' => $j]);
            }
        }

        /* ---------------- Stats / Features / Process ---------------- */
        foreach ([['Projects Completed', 750, '+', 'award'], ['Happy Clients', 600, '+', 'smile'], ['Years of Experience', 12, '+', 'clock'], ['Expert Team Members', 45, '', 'users']] as $i => [$label, $value, $suffix, $icon]) {
            Stat::create(compact('label', 'value', 'suffix', 'icon') + ['sort_order' => $i]);
        }

        foreach ([
            ['Fixed Budget, No Surprises', 'Detailed BOQ before work begins — what we quote is what you pay.', 'dollar'],
            ['On-Time Handover', 'Dedicated project managers keep every milestone on schedule.', 'clock'],
            ['Own Factory', 'In-house production for consistent quality and faster delivery.', 'tool'],
            ['Premium Materials', 'Carefully sourced local and imported materials that last.', 'star'],
            ['Warranty & After-Sales', 'Structural warranty and responsive after-sales support.', 'shield'],
            ['Experienced Designers', 'Award-winning designers who listen and understand you.', 'pen'],
        ] as $i => [$title, $description, $icon]) {
            Feature::create(compact('title', 'description', 'icon') + ['sort_order' => $i]);
        }

        foreach ([
            ['Consultation', 'Free consultation and site visit to understand your needs and budget.', 'message'],
            ['Concept & 3D Design', 'Space planning, mood boards and photorealistic 3D renders.', 'layers'],
            ['Quotation & Agreement', 'Transparent BOQ with fixed pricing and clear timeline.', 'check'],
            ['Execution', 'Our craftsmen build and install with strict quality control.', 'tool'],
            ['Handover', 'Final inspection, deep cleaning and handover with warranty.', 'key'],
        ] as $i => [$title, $description, $icon]) {
            ProcessStep::create(compact('title', 'description', 'icon') + ['sort_order' => $i]);
        }

        /* ---------------- Testimonials / Clients / Team ---------------- */
        foreach ([
            ['Rafiq Ahmed', 'Apartment Owner, Gulshan', 'They understood exactly what we wanted. The 3D design matched the final result perfectly and the handover was on time.'],
            ['Nusrat Jahan', 'Homeowner, Bashundhara', 'Professional team, transparent pricing and excellent finishing. Highly recommended for any home interior.'],
            ['Tanvir Hasan', 'CEO, Tech Startup', 'Our new office has boosted team morale. Great planning and very smooth execution.'],
            ['Farhana Kabir', 'Restaurant Owner', 'Customers love the ambience! The team delivered a unique design within our budget.'],
        ] as $i => [$name, $designation, $message]) {
            Testimonial::create(compact('name', 'designation', 'message') + ['rating' => 5, 'sort_order' => $i]);
        }

        foreach (['Apex Group', 'Northern Tech', 'Bengal Foods', 'Skyline Developers', 'Green Pharma', 'Metro Bank', 'Urban Living', 'Delta Holdings'] as $i => $name) {
            Client::create(['name' => $name, 'sort_order' => $i]);
        }

        foreach ([['Ar. Tanvir Rahman', 'Founder & Principal Designer'], ['Sadia Islam', 'Lead Interior Designer'], ['Mahmud Hossain', 'Project Manager'], ['Ayesha Siddiqua', '3D Visualizer']] as $i => [$name, $designation]) {
            TeamMember::create(['name' => $name, 'designation' => $designation, 'sort_order' => $i]);
        }

        /* ---------------- FAQs ---------------- */
        foreach ([
            ['How much does interior design cost?', 'Cost depends on size, materials and scope. After a free site visit we provide a detailed BOQ with fixed pricing.'],
            ['How long does a typical project take?', 'An average apartment takes 45–60 days after design approval. Larger commercial projects vary by scope.'],
            ['Do you provide 3D designs?', 'Yes. Every project includes photorealistic 3D design so you can visualize the result before execution.'],
            ['Do you work outside Dhaka?', 'Yes, we work across Bangladesh including Chattogram, Sylhet and other major cities.'],
            ['Is there any warranty?', 'We provide structural warranty on furniture and fixtures along with after-sales support.'],
            ['What are the payment terms?', 'Payments are made in stages tied to project milestones, as outlined in our agreement.'],
        ] as $i => [$question, $answer]) {
            Faq::create(compact('question', 'answer') + ['sort_order' => $i]);
        }

        /* ---------------- Blog ---------------- */
        foreach ([
            ['10 Interior Design Trends for Bangladeshi Homes', $img['living2']],
            ['How to Plan a Productive Office Interior', $img['office']],
            ['Choosing the Right Materials for Your Kitchen', $img['kitchen']],
        ] as $i => [$title, $image]) {
            Post::create([
                'title' => $title, 'slug' => Str::slug($title), 'image' => $this->u($image, 1200),
                'excerpt' => 'Practical ideas and expert tips from our design team to help you create a space you love.',
                'body' => '<p>Designing a space is about balancing beauty and function. In this article our designers share practical advice you can apply to your own project.</p><h3>Start with how you live</h3><p>Before choosing colors or furniture, think about daily routines, storage needs and natural light.</p><h3>Invest in quality</h3><p>Durable materials save money in the long run and age beautifully.</p>',
                'published_at' => now()->subDays($i * 10)->toDateString(),
            ]);
        }

        /* ---------------- Pages ---------------- */
        Page::create(['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'content' => '<p>We respect your privacy. Information submitted through our forms is used only to respond to your inquiry and is never sold to third parties.</p>']);
        Page::create(['title' => 'Terms & Conditions', 'slug' => 'terms-and-conditions', 'content' => '<p>By using this website you agree to our terms. Project terms are defined in individual agreements with each client.</p>']);

        /* ---------------- Careers ---------------- */
        Career::create([
            'title' => 'Senior Interior Designer', 'slug' => 'senior-interior-designer', 'location' => 'Dhaka', 'job_type' => 'Full Time',
            'vacancy' => '2', 'salary' => 'Negotiable', 'deadline' => now()->addMonth()->toDateString(),
            'description' => '<h3>Responsibilities</h3><ul><li>Lead residential and commercial design projects</li><li>Prepare concepts, drawings and material selections</li><li>Coordinate with clients and site team</li></ul><h3>Requirements</h3><ul><li>B.Arch / BFA in Interior Design</li><li>3+ years experience</li><li>Proficiency in AutoCAD, SketchUp, 3ds Max</li></ul>',
        ]);
        Career::create([
            'title' => '3D Visualizer', 'slug' => '3d-visualizer', 'location' => 'Dhaka', 'job_type' => 'Full Time',
            'vacancy' => '1', 'salary' => 'Negotiable', 'deadline' => now()->addMonth()->toDateString(),
            'description' => '<h3>Responsibilities</h3><ul><li>Create photorealistic renders and walkthroughs</li></ul><h3>Requirements</h3><ul><li>Expert in 3ds Max + V-Ray / Corona or Lumion</li><li>Strong portfolio</li></ul>',
        ]);
    }
}
