import { lazy, Suspense, useEffect } from 'react'
import { Routes, Route, useLocation } from 'react-router-dom'
import { SiteProvider } from './context/SiteContext'
import Header from './components/Header'
import Footer from './components/Footer'
import FloatingContact from './components/FloatingContact'
import Loader from './components/Loader'
import Home from './pages/Home'
import About from './pages/About'
import Services from './pages/Services'
import ServiceDetail from './pages/ServiceDetail'
import Projects from './pages/Projects'
import ProjectDetail from './pages/ProjectDetail'
import Blog from './pages/Blog'
import BlogDetail from './pages/BlogDetail'
import Careers from './pages/Careers'
import CareerDetail from './pages/CareerDetail'
import Contact from './pages/Contact'
import GetQuote from './pages/GetQuote'
import Faq from './pages/Faq'
import CmsPage from './pages/CmsPage'
import NotFound from './pages/NotFound'

const AdminApp = lazy(() => import('./admin/AdminApp'))

function ScrollToTop() {
  const { pathname } = useLocation()
  useEffect(() => { window.scrollTo(0, 0) }, [pathname])
  return null
}

function PublicSite() {
  return (
    <SiteProvider>
      <ScrollToTop />
      <Header />
      <main>
        <Routes>
          <Route index element={<Home />} />
          <Route path="about" element={<About />} />
          <Route path="services" element={<Services />} />
          <Route path="services/:slug" element={<ServiceDetail />} />
          <Route path="projects" element={<Projects />} />
          <Route path="projects/:slug" element={<ProjectDetail />} />
          <Route path="blog" element={<Blog />} />
          <Route path="blog/:slug" element={<BlogDetail />} />
          <Route path="careers" element={<Careers />} />
          <Route path="careers/:slug" element={<CareerDetail />} />
          <Route path="contact" element={<Contact />} />
          <Route path="get-quote" element={<GetQuote />} />
          <Route path="faq" element={<Faq />} />
          <Route path="page/:slug" element={<CmsPage />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </main>
      <Footer />
      <FloatingContact />
    </SiteProvider>
  )
}

export default function App() {
  return (
    <Routes>
      <Route
        path="/admin/*"
        element={
          <Suspense fallback={<Loader full />}>
            <AdminApp />
          </Suspense>
        }
      />
      <Route path="/*" element={<PublicSite />} />
    </Routes>
  )
}
