import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { Box, CircularProgress } from '@mui/material';

import AppLayout from './components/layout/AppLayout';
import { useAuth } from './contexts/AuthContext';

// Import das páginas
import HomePage from './pages/HomePage';
import JobsPage from './pages/jobs/JobsPage';
import JobDetailPage from './pages/jobs/JobDetailPage';
import LoginPage from './pages/auth/LoginPage';
import RegisterPage from './pages/auth/RegisterPage';
import ForgotPasswordPage from './pages/auth/ForgotPasswordPage';

// Páginas do candidato
import CandidateDashboard from './pages/candidate/Dashboard';
import CandidateProfile from './pages/candidate/Profile';
import CandidateApplications from './pages/candidate/Applications';

// Páginas da empresa
import CompanyDashboard from './pages/company/Dashboard';
import CompanyProfile from './pages/company/Profile';
import CompanyJobs from './pages/company/Jobs';
import CompanyJobCreate from './pages/company/JobCreate';
import CompanyJobEdit from './pages/company/JobEdit';
import CompanyCandidates from './pages/company/Candidates';

// Páginas do admin
import AdminDashboard from './pages/admin/Dashboard';
import AdminUsers from './pages/admin/Users';
import AdminJobs from './pages/admin/Jobs';
import AdminCompanies from './pages/admin/Companies';

// Componente para rota protegida
function ProtectedRoute({ children, allowedUserTypes = [] }) {
  const { isAuthenticated, isLoading, userType } = useAuth();

  if (isLoading) {
    return (
      <Box 
        display="flex" 
        justifyContent="center" 
        alignItems="center" 
        minHeight="100vh"
      >
        <CircularProgress />
      </Box>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (allowedUserTypes.length > 0 && !allowedUserTypes.includes(userType)) {
    return <Navigate to="/" replace />;
  }

  return children;
}

// Componente para rota de convidado (apenas para não autenticados)
function GuestRoute({ children }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <Box 
        display="flex" 
        justifyContent="center" 
        alignItems="center" 
        minHeight="100vh"
      >
        <CircularProgress />
      </Box>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="/" replace />;
  }

  return children;
}

function App() {
  return (
    <>
      <Helmet>
        <title>Plataforma de Empregos - Moçambique</title>
        <meta 
          name="description" 
          content="A maior plataforma de empregos de Moçambique. Encontre sua oportunidade profissional ou o talento ideal para sua empresa." 
        />
        <meta name="keywords" content="emprego, trabalho, vagas, Moçambique, carreira, recrutamento" />
        <link rel="canonical" href="https://empregomz.com" />
      </Helmet>

      <AppLayout>
        <Routes>
          {/* Rotas públicas */}
          <Route path="/" element={<HomePage />} />
          <Route path="/vagas" element={<JobsPage />} />
          <Route path="/vagas/:id" element={<JobDetailPage />} />

          {/* Rotas de autenticação (apenas para não autenticados) */}
          <Route 
            path="/login" 
            element={
              <GuestRoute>
                <LoginPage />
              </GuestRoute>
            } 
          />
          <Route 
            path="/register" 
            element={
              <GuestRoute>
                <RegisterPage />
              </GuestRoute>
            } 
          />
          <Route 
            path="/forgot-password" 
            element={
              <GuestRoute>
                <ForgotPasswordPage />
              </GuestRoute>
            } 
          />

          {/* Rotas do candidato */}
          <Route 
            path="/candidate/dashboard" 
            element={
              <ProtectedRoute allowedUserTypes={['candidate']}>
                <CandidateDashboard />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/candidate/profile" 
            element={
              <ProtectedRoute allowedUserTypes={['candidate']}>
                <CandidateProfile />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/candidate/applications" 
            element={
              <ProtectedRoute allowedUserTypes={['candidate']}>
                <CandidateApplications />
              </ProtectedRoute>
            } 
          />

          {/* Rotas da empresa */}
          <Route 
            path="/company/dashboard" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyDashboard />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/company/profile" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyProfile />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/company/jobs" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyJobs />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/company/jobs/create" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyJobCreate />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/company/jobs/:id/edit" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyJobEdit />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/company/candidates" 
            element={
              <ProtectedRoute allowedUserTypes={['company']}>
                <CompanyCandidates />
              </ProtectedRoute>
            } 
          />

          {/* Rotas do administrador */}
          <Route 
            path="/admin/dashboard" 
            element={
              <ProtectedRoute allowedUserTypes={['admin']}>
                <AdminDashboard />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/admin/users" 
            element={
              <ProtectedRoute allowedUserTypes={['admin']}>
                <AdminUsers />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/admin/jobs" 
            element={
              <ProtectedRoute allowedUserTypes={['admin']}>
                <AdminJobs />
              </ProtectedRoute>
            } 
          />
          <Route 
            path="/admin/companies" 
            element={
              <ProtectedRoute allowedUserTypes={['admin']}>
                <AdminCompanies />
              </ProtectedRoute>
            } 
          />

          {/* Página 404 */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AppLayout>
    </>
  );
}

export default App;