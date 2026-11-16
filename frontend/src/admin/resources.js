import {
  FiImage, FiLayers, FiFolder, FiGrid, FiMessageCircle, FiBriefcase, FiUsers, FiBarChart2,
  FiList, FiAward, FiHelpCircle, FiEdit, FiFileText, FiUserPlus, FiInbox,
} from 'react-icons/fi'
import { ICON_KEYS } from '../lib/icons'

/*
 * Admin resource definitions. Field types:
 * text | textarea | rich | number | switch | select | date | image | gallery | icon | color
 * `half: true` places a field in a two-column row.
 * Keys must match backend app/Support/ResourceRegistry.php
 */

const sort = { name: 'sort_order', label: 'Display Order', type: 'number', half: true, default: 0, help: 'Lower numbers show first' }
const active = { name: 'is_active', label: 'Active (visible on website)', type: 'switch', default: true, half: true }
const iconField = { name: 'icon', label: 'Icon', type: 'icon', half: true, options: ICON_KEYS }
const activeCol = { key: 'is_active', label: 'Status', type: 'bool' }

export const RESOURCES = {
  sliders: {
    label: 'Hero Sliders', singular: 'Slider', icon: FiImage, group: 'Home Page',
    columns: [{ key: 'image', type: 'image' }, { key: 'title', label: 'Title' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [
      { name: 'title', label: 'Heading' },
      { name: 'subtitle', label: 'Sub-heading', type: 'textarea' },
      { name: 'image', label: 'Background Image', type: 'image', help: 'Recommended 1920 × 900 px' },
      { name: 'button_text', label: 'Button Text', half: true },
      { name: 'button_link', label: 'Button Link', half: true, help: 'e.g. /get-quote or https://…' },
      sort, active,
    ],
  },

  stats: {
    label: 'Counters', singular: 'Counter', icon: FiBarChart2, group: 'Home Page',
    columns: [{ key: 'label', label: 'Label' }, { key: 'value', label: 'Value' }, { key: 'suffix', label: 'Suffix' }, activeCol],
    fields: [
      { name: 'label', label: 'Label', required: true },
      { name: 'value', label: 'Number', type: 'number', required: true, half: true },
      { name: 'suffix', label: 'Suffix', half: true, help: 'e.g. + or %' },
      iconField, sort, active,
    ],
  },

  features: {
    label: 'Why Choose Us', singular: 'Feature', icon: FiAward, group: 'Home Page',
    columns: [{ key: 'title', label: 'Title' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [{ name: 'title', label: 'Title', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, iconField, sort, active],
  },

  process_steps: {
    label: 'Work Process', singular: 'Step', icon: FiList, group: 'Home Page',
    columns: [{ key: 'title', label: 'Title' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [{ name: 'title', label: 'Title', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, iconField, sort, active],
  },

  services: {
    label: 'Services', singular: 'Service', icon: FiLayers, group: 'Content',
    columns: [{ key: 'image', type: 'image' }, { key: 'title', label: 'Title' }, { key: 'is_featured', label: 'Home', type: 'bool' }, activeCol],
    fields: [
      { name: 'title', label: 'Title', required: true, half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      { name: 'short_description', label: 'Short Description', type: 'textarea' },
      { name: 'description', label: 'Full Description', type: 'rich' },
      { name: 'image', label: 'Image', type: 'image' },
      iconField,
      { name: 'is_featured', label: 'Show on home page', type: 'switch', half: true, default: true },
      sort, active,
    ],
  },

  project_categories: {
    label: 'Project Categories', singular: 'Category', icon: FiFolder, group: 'Content',
    columns: [{ key: 'name', label: 'Name' }, { key: 'slug', label: 'Slug' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [
      { name: 'name', label: 'Name', required: true, half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      sort, active,
    ],
  },

  projects: {
    label: 'Projects', singular: 'Project', icon: FiGrid, group: 'Content',
    filter: { name: 'project_category_id', label: 'All categories', source: 'project_categories', labelKey: 'name' },
    columns: [
      { key: 'cover_image', type: 'image' }, { key: 'title', label: 'Title' }, { key: 'category.name', label: 'Category' },
      { key: 'location', label: 'Location' }, { key: 'is_featured', label: 'Featured', type: 'bool' }, activeCol,
    ],
    fields: [
      { name: 'title', label: 'Project Title', required: true, half: true },
      { name: 'project_category_id', label: 'Category', type: 'select', source: 'project_categories', labelKey: 'name', half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      { name: 'client_name', label: 'Client Name', half: true },
      { name: 'location', label: 'Location', half: true },
      { name: 'area', label: 'Area Size', half: true, help: 'e.g. 2,400 sqft' },
      { name: 'year', label: 'Year', half: true },
      { name: 'video_url', label: 'Video / Virtual Tour URL', half: true, help: 'YouTube, Vimeo or any link' },
      { name: 'short_description', label: 'Short Description', type: 'textarea' },
      { name: 'description', label: 'Details', type: 'rich' },
      { name: 'cover_image', label: 'Cover Image', type: 'image' },
      { name: 'images', label: 'Gallery Images', type: 'gallery', help: 'You can select multiple images at once' },
      { name: 'is_featured', label: 'Featured on home page', type: 'switch', half: true },
      sort, active,
    ],
  },

  testimonials: {
    label: 'Testimonials', singular: 'Testimonial', icon: FiMessageCircle, group: 'Content',
    columns: [{ key: 'photo', type: 'image' }, { key: 'name', label: 'Name' }, { key: 'designation', label: 'Designation' }, { key: 'rating', label: 'Rating' }, activeCol],
    fields: [
      { name: 'name', label: 'Client Name', required: true, half: true },
      { name: 'designation', label: 'Designation / Location', half: true },
      { name: 'message', label: 'Review', type: 'textarea', required: true },
      { name: 'rating', label: 'Rating', type: 'select', options: [5, 4, 3, 2, 1].map((n) => ({ value: n, label: `${n} star` })), default: 5, half: true },
      { name: 'video_url', label: 'Video Review URL (optional)', half: true },
      { name: 'photo', label: 'Photo', type: 'image' },
      sort, active,
    ],
  },

  clients: {
    label: 'Clients / Partners', singular: 'Client', icon: FiBriefcase, group: 'Content',
    columns: [{ key: 'logo', type: 'image' }, { key: 'name', label: 'Name' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [
      { name: 'name', label: 'Name', required: true, half: true },
      { name: 'website', label: 'Website', half: true },
      { name: 'logo', label: 'Logo', type: 'image', help: 'Transparent PNG/SVG works best' },
      sort, active,
    ],
  },

  team_members: {
    label: 'Team Members', singular: 'Member', icon: FiUsers, group: 'Content',
    columns: [{ key: 'photo', type: 'image' }, { key: 'name', label: 'Name' }, { key: 'designation', label: 'Designation' }, activeCol],
    fields: [
      { name: 'name', label: 'Name', required: true, half: true },
      { name: 'designation', label: 'Designation', half: true },
      { name: 'bio', label: 'Short Bio', type: 'textarea' },
      { name: 'facebook', label: 'Facebook URL', half: true },
      { name: 'linkedin', label: 'LinkedIn URL', half: true },
      { name: 'photo', label: 'Photo', type: 'image' },
      sort, active,
    ],
  },

  faqs: {
    label: 'FAQs', singular: 'FAQ', icon: FiHelpCircle, group: 'Content',
    columns: [{ key: 'question', label: 'Question' }, { key: 'sort_order', label: 'Order' }, activeCol],
    fields: [{ name: 'question', label: 'Question', required: true }, { name: 'answer', label: 'Answer', type: 'textarea', required: true }, sort, active],
  },

  posts: {
    label: 'Blog Posts', singular: 'Post', icon: FiEdit, group: 'Content',
    columns: [{ key: 'image', type: 'image' }, { key: 'title', label: 'Title' }, { key: 'published_at', label: 'Date', type: 'date' }, activeCol],
    fields: [
      { name: 'title', label: 'Title', required: true, half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      { name: 'excerpt', label: 'Excerpt', type: 'textarea' },
      { name: 'body', label: 'Content', type: 'rich' },
      { name: 'image', label: 'Featured Image', type: 'image' },
      { name: 'published_at', label: 'Publish Date', type: 'date', half: true },
      active,
    ],
  },

  pages: {
    label: 'Pages', singular: 'Page', icon: FiFileText, group: 'Content',
    columns: [{ key: 'title', label: 'Title' }, { key: 'slug', label: 'URL', get: (r) => `/page/${r.slug}` }, { key: 'show_in_footer', label: 'Footer', type: 'bool' }, activeCol],
    fields: [
      { name: 'title', label: 'Title', required: true, half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      { name: 'content', label: 'Content', type: 'rich' },
      { name: 'show_in_footer', label: 'Show link in footer', type: 'switch', default: true, half: true },
      active,
    ],
  },

  careers: {
    label: 'Job Posts', singular: 'Job', icon: FiBriefcase, group: 'Careers',
    columns: [{ key: 'title', label: 'Title' }, { key: 'location', label: 'Location' }, { key: 'deadline', label: 'Deadline', type: 'date' }, activeCol],
    fields: [
      { name: 'title', label: 'Job Title', required: true, half: true },
      { name: 'slug', label: 'URL Slug', half: true, help: 'Leave empty to auto-generate' },
      { name: 'location', label: 'Location', half: true },
      { name: 'job_type', label: 'Job Type', half: true, help: 'Full Time / Part Time / Contract' },
      { name: 'vacancy', label: 'Vacancy', half: true },
      { name: 'salary', label: 'Salary', half: true },
      { name: 'deadline', label: 'Deadline', type: 'date', half: true },
      { name: 'description', label: 'Job Description', type: 'rich' },
      active,
    ],
  },

  job_applications: {
    label: 'Applications', singular: 'Application', icon: FiUserPlus, group: 'Careers', readonly: true,
    filter: { name: 'status', label: 'All status', options: ['new', 'shortlisted', 'rejected', 'hired'] },
    columns: [
      { key: 'name', label: 'Name' }, { key: 'career.title', label: 'Job' }, { key: 'phone', label: 'Phone' },
      { key: 'status', label: 'Status', type: 'badge' }, { key: 'created_at', label: 'Date', type: 'date' },
    ],
    view: [
      { key: 'career.title', label: 'Applied For' }, { key: 'name', label: 'Name' }, { key: 'email', label: 'Email' },
      { key: 'phone', label: 'Phone' }, { key: 'cover_letter', label: 'Cover Letter', long: true }, { key: 'created_at', label: 'Submitted', type: 'datetime' },
    ],
    fields: [
      { name: 'status', label: 'Status', type: 'select', options: ['new', 'shortlisted', 'rejected', 'hired'], required: true },
      { name: 'admin_note', label: 'Internal Note', type: 'textarea' },
    ],
  },

  inquiries: {
    label: 'Quote Requests', singular: 'Inquiry', icon: FiInbox, group: 'Leads', readonly: true,
    filter: { name: 'status', label: 'All status', options: ['new', 'contacted', 'converted', 'closed'] },
    columns: [
      { key: 'name', label: 'Name' }, { key: 'phone', label: 'Phone' }, { key: 'type', label: 'Type' },
      { key: 'service', label: 'Service' }, { key: 'status', label: 'Status', type: 'badge' }, { key: 'created_at', label: 'Date', type: 'date' },
    ],
    view: [
      { key: 'type', label: 'Form' }, { key: 'name', label: 'Name' }, { key: 'phone', label: 'Phone' }, { key: 'email', label: 'Email' },
      { key: 'service', label: 'Service' }, { key: 'property_type', label: 'Property Type' }, { key: 'area_size', label: 'Area Size' },
      { key: 'budget', label: 'Budget' }, { key: 'location', label: 'Location' }, { key: 'message', label: 'Message', long: true },
      { key: 'created_at', label: 'Submitted', type: 'datetime' },
    ],
    fields: [
      { name: 'status', label: 'Status', type: 'select', options: ['new', 'contacted', 'converted', 'closed'], required: true },
      { name: 'admin_note', label: 'Internal Note', type: 'textarea' },
    ],
  },
}

export const GROUPS = ['Leads', 'Home Page', 'Content', 'Careers']

export function getPath(obj, path) {
  return path.split('.').reduce((o, k) => (o == null ? o : o[k]), obj)
}
