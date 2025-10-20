import React, { useState, useEffect } from 'react';
import {
  Box,
  Container,
  Paper,
  TextField,
  Button,
  Typography,
  Link,
  Alert,
  InputAdornment,
  IconButton,
  FormControlLabel,
  Checkbox,
  Divider,
  Stack,
  CircularProgress,
  Tabs,
  Tab,
  Grid,
  Chip
} from '@mui/material';
import {
  Visibility,
  VisibilityOff,
  Email as EmailIcon,
  Lock as LockIcon,
  Person as PersonIcon,
  Business as BusinessIcon,
  Phone as PhoneIcon,
  LocationOn as LocationIcon,
  Language as LanguageIcon,
  CloudUpload as UploadIcon
} from '@mui/icons-material';
import { useNavigate, useLocation } from 'react-router-dom';
import { useForm, Controller } from 'react-hook-form';
import { yupResolver } from '@hookform/resolvers/yup';
import * as yup from 'yup';
import { useSnackbar } from 'notistack';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '../../contexts/AuthContext';

// Schemas de validação
const candidateSchema = yup.object().shape({
  name: yup.string().required('Nome é obrigatório').min(2, 'Nome deve ter pelo menos 2 caracteres'),
  email: yup.string().email('Email inválido').required('Email é obrigatório'),
  phone: yup.string().required('Telefone é obrigatório'),
  location: yup.string().required('Localização é obrigatória'),
  password: yup.string().min(6, 'Senha deve ter pelo menos 6 caracteres').required('Senha é obrigatória'),
  confirmPassword: yup.string().oneOf([yup.ref('password')], 'Senhas não coincidem').required('Confirmação de senha é obrigatória'),
  termsAccepted: yup.boolean().oneOf([true], 'Deve aceitar os termos e condições')
});

const companySchema = yup.object().shape({
  companyName: yup.string().required('Nome da empresa é obrigatório').min(2, 'Nome deve ter pelo menos 2 caracteres'),
  nuit: yup.string().required('NUIT é obrigatório'),
  email: yup.string().email('Email inválido').required('Email é obrigatório'),
  phone: yup.string().required('Telefone é obrigatório'),
  address: yup.string().required('Endereço é obrigatório'),
  website: yup.string().url('URL inválida'),
  industry: yup.string().required('Sector de atividade é obrigatório'),
  description: yup.string().required('Descrição da empresa é obrigatória').min(50, 'Descrição deve ter pelo menos 50 caracteres'),
  contactPersonName: yup.string().required('Nome do responsável é obrigatório'),
  contactPersonRole: yup.string().required('Cargo do responsável é obrigatório'),
  password: yup.string().min(6, 'Senha deve ter pelo menos 6 caracteres').required('Senha é obrigatória'),
  confirmPassword: yup.string().oneOf([yup.ref('password')], 'Senhas não coincidem').required('Confirmação de senha é obrigatória'),
  termsAccepted: yup.boolean().oneOf([true], 'Deve aceitar os termos e condições')
});

function TabPanel({ children, value, index }) {
  return (
    <div hidden={value !== index}>
      {value === index && <Box>{children}</Box>}
    </div>
  );
}

function RegisterPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { enqueueSnackbar } = useSnackbar();
  const { register } = useAuth();
  
  const [activeTab, setActiveTab] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  // Pegar tipo de usuário da URL
  const urlParams = new URLSearchParams(location.search);
  const userTypeFromUrl = urlParams.get('type');

  useEffect(() => {
    if (userTypeFromUrl === 'company') {
      setActiveTab(1);
    } else {
      setActiveTab(0);
    }
  }, [userTypeFromUrl]);

  // Formulário do candidato
  const candidateForm = useForm({
    resolver: yupResolver(candidateSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      location: '',
      password: '',
      confirmPassword: '',
      termsAccepted: false
    }
  });

  // Formulário da empresa
  const companyForm = useForm({
    resolver: yupResolver(companySchema),
    defaultValues: {
      companyName: '',
      nuit: '',
      email: '',
      phone: '',
      address: '',
      website: '',
      industry: '',
      description: '',
      contactPersonName: '',
      contactPersonRole: '',
      password: '',
      confirmPassword: '',
      termsAccepted: false
    }
  });

  const handleTabChange = (event, newValue) => {
    setActiveTab(newValue);
  };

  const onSubmitCandidate = async (data) => {
    setIsLoading(true);
    
    try {
      const result = await register({
        name: data.name,
        email: data.email,
        phone: data.phone,
        location: data.location,
        password: data.password
      }, 'candidate');
      
      if (result.success) {
        enqueueSnackbar('Conta criada com sucesso! Bem-vindo!', { variant: 'success' });
        navigate('/candidate/dashboard');
      } else {
        candidateForm.setError('root', {
          type: 'manual',
          message: result.message || 'Erro no registo. Tente novamente.'
        });
      }
    } catch (error) {
      console.error('Erro no registo:', error);
      candidateForm.setError('root', {
        type: 'manual',
        message: 'Erro interno. Tente novamente mais tarde.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const onSubmitCompany = async (data) => {
    setIsLoading(true);
    
    try {
      const result = await register({
        companyName: data.companyName,
        nuit: data.nuit,
        email: data.email,
        phone: data.phone,
        address: data.address,
        website: data.website,
        industry: data.industry,
        description: data.description,
        contactPersonName: data.contactPersonName,
        contactPersonRole: data.contactPersonRole,
        password: data.password
      }, 'company');
      
      if (result.success) {
        enqueueSnackbar('Empresa registada com sucesso! Aguarde aprovação.', { variant: 'success' });
        navigate('/company/dashboard');
      } else {
        companyForm.setError('root', {
          type: 'manual',
          message: result.message || 'Erro no registo. Tente novamente.'
        });
      }
    } catch (error) {
      console.error('Erro no registo:', error);
      companyForm.setError('root', {
        type: 'manual',
        message: 'Erro interno. Tente novamente mais tarde.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Criar Conta - Plataforma de Empregos Moçambique</title>
        <meta name="description" content="Crie sua conta na Plataforma de Empregos de Moçambique" />
      </Helmet>

      <Box
        sx={{
          minHeight: '100vh',
          py: 4,
          background: 'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)'
        }}
      >
        <Container maxWidth="md">
          <Paper 
            elevation={10}
            sx={{
              p: { xs: 3, sm: 4 },
              borderRadius: 3,
              background: 'rgba(255,255,255,0.95)',
              backdropFilter: 'blur(10px)'
            }}
          >
            {/* Header */}
            <Box sx={{ textAlign: 'center', mb: 4 }}>
              <Typography 
                variant="h4" 
                component="h1" 
                fontWeight="bold" 
                color="primary" 
                gutterBottom
              >
                🇲🇿 Emprego MZ
              </Typography>
              <Typography variant="h5" fontWeight="600" gutterBottom>
                Criar Nova Conta
              </Typography>
              <Typography variant="body1" color="text.secondary">
                Junte-se à maior plataforma de empregos de Moçambique
              </Typography>
            </Box>

            {/* Tabs */}
            <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 4 }}>
              <Tabs 
                value={activeTab} 
                onChange={handleTabChange} 
                centered
                variant="fullWidth"
              >
                <Tab 
                  label="Candidato" 
                  icon={<PersonIcon />} 
                  iconPosition="start"
                  sx={{ minHeight: 60 }}
                />
                <Tab 
                  label="Empresa" 
                  icon={<BusinessIcon />} 
                  iconPosition="start"
                  sx={{ minHeight: 60 }}
                />
              </Tabs>
            </Box>

            {/* Formulário do Candidato */}
            <TabPanel value={activeTab} index={0}>
              <Box component="form" onSubmit={candidateForm.handleSubmit(onSubmitCandidate)}>
                {candidateForm.formState.errors.root && (
                  <Alert severity="error" sx={{ mb: 3 }}>
                    {candidateForm.formState.errors.root.message}
                  </Alert>
                )}

                <Grid container spacing={3}>
                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="name"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Nome Completo"
                          error={!!candidateForm.formState.errors.name}
                          helperText={candidateForm.formState.errors.name?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <PersonIcon color={candidateForm.formState.errors.name ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="email"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Email"
                          type="email"
                          error={!!candidateForm.formState.errors.email}
                          helperText={candidateForm.formState.errors.email?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <EmailIcon color={candidateForm.formState.errors.email ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="phone"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Telefone"
                          placeholder="+258 XX XXX XXXX"
                          error={!!candidateForm.formState.errors.phone}
                          helperText={candidateForm.formState.errors.phone?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <PhoneIcon color={candidateForm.formState.errors.phone ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="location"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Localização"
                          placeholder="Maputo, Beira, Nampula..."
                          error={!!candidateForm.formState.errors.location}
                          helperText={candidateForm.formState.errors.location?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LocationIcon color={candidateForm.formState.errors.location ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="password"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Senha"
                          type={showPassword ? 'text' : 'password'}
                          error={!!candidateForm.formState.errors.password}
                          helperText={candidateForm.formState.errors.password?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LockIcon color={candidateForm.formState.errors.password ? 'error' : 'action'} />
                              </InputAdornment>
                            ),
                            endAdornment: (
                              <InputAdornment position="end">
                                <IconButton
                                  onClick={() => setShowPassword(!showPassword)}
                                  edge="end"
                                >
                                  {showPassword ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="confirmPassword"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Confirmar Senha"
                          type={showConfirmPassword ? 'text' : 'password'}
                          error={!!candidateForm.formState.errors.confirmPassword}
                          helperText={candidateForm.formState.errors.confirmPassword?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LockIcon color={candidateForm.formState.errors.confirmPassword ? 'error' : 'action'} />
                              </InputAdornment>
                            ),
                            endAdornment: (
                              <InputAdornment position="end">
                                <IconButton
                                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                  edge="end"
                                >
                                  {showConfirmPassword ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12}>
                    <Controller
                      name="termsAccepted"
                      control={candidateForm.control}
                      render={({ field }) => (
                        <FormControlLabel
                          control={
                            <Checkbox
                              {...field}
                              checked={field.value}
                              color="primary"
                            />
                          }
                          label={
                            <Typography variant="body2">
                              Aceito os{' '}
                              <Link href="/terms" target="_blank" color="primary">
                                Termos e Condições
                              </Link>
                              {' '}e a{' '}
                              <Link href="/privacy" target="_blank" color="primary">
                                Política de Privacidade
                              </Link>
                            </Typography>
                          }
                        />
                      )}
                    />
                    {candidateForm.formState.errors.termsAccepted && (
                      <Typography variant="caption" color="error">
                        {candidateForm.formState.errors.termsAccepted.message}
                      </Typography>
                    )}
                  </Grid>

                  <Grid item xs={12}>
                    <Button
                      type="submit"
                      fullWidth
                      variant="contained"
                      size="large"
                      disabled={isLoading}
                      sx={{ py: 1.5, fontSize: '1.1rem', fontWeight: 600 }}
                    >
                      {isLoading ? (
                        <>
                          <CircularProgress size={20} sx={{ mr: 1 }} />
                          Criando conta...
                        </>
                      ) : (
                        'Criar Conta de Candidato'
                      )}
                    </Button>
                  </Grid>
                </Grid>
              </Box>
            </TabPanel>

            {/* Formulário da Empresa */}
            <TabPanel value={activeTab} index={1}>
              <Box component="form" onSubmit={companyForm.handleSubmit(onSubmitCompany)}>
                {companyForm.formState.errors.root && (
                  <Alert severity="error" sx={{ mb: 3 }}>
                    {companyForm.formState.errors.root.message}
                  </Alert>
                )}

                <Typography variant="h6" gutterBottom color="primary">
                  Informações da Empresa
                </Typography>
                
                <Grid container spacing={3} sx={{ mb: 4 }}>
                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="companyName"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Nome da Empresa"
                          error={!!companyForm.formState.errors.companyName}
                          helperText={companyForm.formState.errors.companyName?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <BusinessIcon color={companyForm.formState.errors.companyName ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="nuit"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="NUIT"
                          placeholder="Número Único de Identificação Tributária"
                          error={!!companyForm.formState.errors.nuit}
                          helperText={companyForm.formState.errors.nuit?.message}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="email"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Email da Empresa"
                          type="email"
                          error={!!companyForm.formState.errors.email}
                          helperText={companyForm.formState.errors.email?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <EmailIcon color={companyForm.formState.errors.email ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="phone"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Telefone"
                          placeholder="+258 XX XXX XXXX"
                          error={!!companyForm.formState.errors.phone}
                          helperText={companyForm.formState.errors.phone?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <PhoneIcon color={companyForm.formState.errors.phone ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12}>
                    <Controller
                      name="address"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Endereço Completo"
                          error={!!companyForm.formState.errors.address}
                          helperText={companyForm.formState.errors.address?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LocationIcon color={companyForm.formState.errors.address ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="website"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Website (opcional)"
                          placeholder="https://www.empresa.com"
                          error={!!companyForm.formState.errors.website}
                          helperText={companyForm.formState.errors.website?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LanguageIcon color={companyForm.formState.errors.website ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="industry"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Sector de Atividade"
                          placeholder="ex: Tecnologia, Saúde, Educação"
                          error={!!companyForm.formState.errors.industry}
                          helperText={companyForm.formState.errors.industry?.message}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12}>
                    <Controller
                      name="description"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Descrição da Empresa"
                          multiline
                          rows={4}
                          placeholder="Descreva a missão, visão e atividades principais da empresa..."
                          error={!!companyForm.formState.errors.description}
                          helperText={companyForm.formState.errors.description?.message}
                        />
                      )}
                    />
                  </Grid>
                </Grid>

                <Typography variant="h6" gutterBottom color="primary">
                  Pessoa de Contacto
                </Typography>
                
                <Grid container spacing={3} sx={{ mb: 4 }}>
                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="contactPersonName"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Nome do Responsável"
                          error={!!companyForm.formState.errors.contactPersonName}
                          helperText={companyForm.formState.errors.contactPersonName?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <PersonIcon color={companyForm.formState.errors.contactPersonName ? 'error' : 'action'} />
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="contactPersonRole"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Cargo do Responsável"
                          placeholder="ex: Diretor de RH, CEO"
                          error={!!companyForm.formState.errors.contactPersonRole}
                          helperText={companyForm.formState.errors.contactPersonRole?.message}
                        />
                      )}
                    />
                  </Grid>
                </Grid>

                <Typography variant="h6" gutterBottom color="primary">
                  Credenciais de Acesso
                </Typography>
                
                <Grid container spacing={3}>
                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="password"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Senha"
                          type={showPassword ? 'text' : 'password'}
                          error={!!companyForm.formState.errors.password}
                          helperText={companyForm.formState.errors.password?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LockIcon color={companyForm.formState.errors.password ? 'error' : 'action'} />
                              </InputAdornment>
                            ),
                            endAdornment: (
                              <InputAdornment position="end">
                                <IconButton
                                  onClick={() => setShowPassword(!showPassword)}
                                  edge="end"
                                >
                                  {showPassword ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12} sm={6}>
                    <Controller
                      name="confirmPassword"
                      control={companyForm.control}
                      render={({ field }) => (
                        <TextField
                          {...field}
                          fullWidth
                          label="Confirmar Senha"
                          type={showConfirmPassword ? 'text' : 'password'}
                          error={!!companyForm.formState.errors.confirmPassword}
                          helperText={companyForm.formState.errors.confirmPassword?.message}
                          InputProps={{
                            startAdornment: (
                              <InputAdornment position="start">
                                <LockIcon color={companyForm.formState.errors.confirmPassword ? 'error' : 'action'} />
                              </InputAdornment>
                            ),
                            endAdornment: (
                              <InputAdornment position="end">
                                <IconButton
                                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                  edge="end"
                                >
                                  {showConfirmPassword ? <VisibilityOff /> : <Visibility />}
                                </IconButton>
                              </InputAdornment>
                            )
                          }}
                        />
                      )}
                    />
                  </Grid>

                  <Grid item xs={12}>
                    <Controller
                      name="termsAccepted"
                      control={companyForm.control}
                      render={({ field }) => (
                        <FormControlLabel
                          control={
                            <Checkbox
                              {...field}
                              checked={field.value}
                              color="primary"
                            />
                          }
                          label={
                            <Typography variant="body2">
                              Aceito os{' '}
                              <Link href="/terms" target="_blank" color="primary">
                                Termos e Condições
                              </Link>
                              {' '}e a{' '}
                              <Link href="/privacy" target="_blank" color="primary">
                                Política de Privacidade
                              </Link>
                            </Typography>
                          }
                        />
                      )}
                    />
                    {companyForm.formState.errors.termsAccepted && (
                      <Typography variant="caption" color="error">
                        {companyForm.formState.errors.termsAccepted.message}
                      </Typography>
                    )}
                  </Grid>

                  <Grid item xs={12}>
                    <Alert severity="info" sx={{ mb: 2 }}>
                      Após o registo, sua empresa passará por um processo de aprovação que pode levar até 2 dias úteis.
                    </Alert>
                  </Grid>

                  <Grid item xs={12}>
                    <Button
                      type="submit"
                      fullWidth
                      variant="contained"
                      size="large"
                      disabled={isLoading}
                      sx={{ py: 1.5, fontSize: '1.1rem', fontWeight: 600 }}
                    >
                      {isLoading ? (
                        <>
                          <CircularProgress size={20} sx={{ mr: 1 }} />
                          Registando empresa...
                        </>
                      ) : (
                        'Registar Empresa'
                      )}
                    </Button>
                  </Grid>
                </Grid>
              </Box>
            </TabPanel>

            <Divider sx={{ my: 4 }}>
              <Typography variant="body2" color="text.secondary">
                ou
              </Typography>
            </Divider>

            {/* Link para login */}
            <Box sx={{ textAlign: 'center' }}>
              <Typography variant="body2" color="text.secondary">
                Já tem uma conta?{' '}
                <Link
                  component="button"
                  type="button"
                  variant="body2"
                  onClick={(e) => {
                    e.preventDefault();
                    navigate('/login');
                  }}
                  sx={{ 
                    textDecoration: 'none',
                    '&:hover': { textDecoration: 'underline' }
                  }}
                >
                  Fazer login
                </Link>
              </Typography>
            </Box>
          </Paper>

          {/* Footer */}
          <Typography 
            variant="body2" 
            color="text.secondary" 
            align="center" 
            sx={{ mt: 4 }}
          >
            © 2025 Plataforma de Empregos Moçambique. Todos os direitos reservados.
          </Typography>
        </Container>
      </Box>
    </>
  );
}

export default RegisterPage;