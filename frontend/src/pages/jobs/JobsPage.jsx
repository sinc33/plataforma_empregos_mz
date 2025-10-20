import React, { useState, useEffect } from 'react';
import {
  Container,
  Typography,
  Box,
  Grid,
  Card,
  CardContent,
  TextField,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  Chip,
  Button,
  Avatar,
  Pagination,
  Paper,
  InputAdornment,
  Skeleton
} from '@mui/material';
import {
  Search as SearchIcon,
  LocationOn,
  Work,
  Business,
  AccessTime,
  FilterList
} from '@mui/icons-material';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';

function JobsPage() {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [jobs, setJobs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [totalPages, setTotalPages] = useState(1);
  const [currentPage, setCurrentPage] = useState(1);

  // Filtros
  const [filters, setFilters] = useState({
    search: searchParams.get('search') || '',
    location: searchParams.get('location') || '',
    category: searchParams.get('category') || '',
    jobType: searchParams.get('jobType') || '',
    experience: searchParams.get('experience') || ''
  });

  // Mock data para demonstração
  const mockJobs = [
    {
      id: 1,
      title: 'Desenvolvedor Full Stack',
      company: 'TechMoz Solutions',
      location: 'Maputo',
      salary: '45.000 - 65.000 MZN',
      jobType: 'Tempo Integral',
      category: 'Tecnologia',
      experience: 'Médio',
      description: 'Desenvolvimento de aplicações web modernas usando React e Node.js',
      postedAt: '2 dias atrás',
      logo: '/api/placeholder/50/50'
    },
    {
      id: 2,
      title: 'Gestor de Marketing Digital',
      company: 'Digital Moçambique',
      location: 'Beira',
      salary: '35.000 - 50.000 MZN',
      jobType: 'Híbrido',
      category: 'Marketing',
      experience: 'Sénior',
      description: 'Gestão de campanhas digitais e estratégias de marketing online',
      postedAt: '1 semana atrás',
      logo: '/api/placeholder/50/50'
    },
    {
      id: 3,
      title: 'Analista Financeiro',
      company: 'Banco Comercial MZ',
      location: 'Maputo',
      salary: '40.000 - 55.000 MZN',
      jobType: 'Presencial',
      category: 'Finanças',
      experience: 'Júnior',
      description: 'Análise financeira e elaboração de relatórios',
      postedAt: '3 dias atrás',
      logo: '/api/placeholder/50/50'
    }
  ];

  const jobCategories = [
    'Tecnologia', 'Saúde', 'Educação', 'Finanças', 
    'Marketing', 'Engenharia', 'Vendas', 'Recursos Humanos'
  ];

  const locations = [
    'Maputo', 'Beira', 'Nampula', 'Matola', 'Quelimane', 
    'Tete', 'Xai-Xai', 'Pemba', 'Chimoio', 'Lichinga'
  ];

  const jobTypes = [
    'Tempo Integral', 'Meio Tempo', 'Estágio', 
    'Freelancer', 'Remoto', 'Híbrido', 'Presencial'
  ];

  const experienceLevels = [
    'Estágio', 'Júnior', 'Médio', 'Sénior', 'Executivo'
  ];

  useEffect(() => {
    fetchJobs();
  }, [filters, currentPage]);

  const fetchJobs = async () => {
    setLoading(true);
    
    // Simular chamada à API
    setTimeout(() => {
      let filteredJobs = mockJobs;
      
      if (filters.search) {
        filteredJobs = filteredJobs.filter(job => 
          job.title.toLowerCase().includes(filters.search.toLowerCase()) ||
          job.company.toLowerCase().includes(filters.search.toLowerCase())
        );
      }
      
      if (filters.location) {
        filteredJobs = filteredJobs.filter(job => job.location === filters.location);
      }
      
      if (filters.category) {
        filteredJobs = filteredJobs.filter(job => job.category === filters.category);
      }
      
      if (filters.jobType) {
        filteredJobs = filteredJobs.filter(job => job.jobType === filters.jobType);
      }
      
      if (filters.experience) {
        filteredJobs = filteredJobs.filter(job => job.experience === filters.experience);
      }
      
      setJobs(filteredJobs);
      setTotalPages(Math.ceil(filteredJobs.length / 10));
      setLoading(false);
    }, 1000);
  };

  const handleFilterChange = (field, value) => {
    const newFilters = { ...filters, [field]: value };
    setFilters(newFilters);
    setCurrentPage(1);
    
    // Atualizar URL
    const params = new URLSearchParams();
    Object.entries(newFilters).forEach(([key, val]) => {
      if (val) params.set(key, val);
    });
    setSearchParams(params);
  };

  const clearFilters = () => {
    setFilters({
      search: '',
      location: '',
      category: '',
      jobType: '',
      experience: ''
    });
    setSearchParams({});
  };

  const JobCard = ({ job }) => (
    <Card 
      sx={{ 
        cursor: 'pointer',
        transition: 'transform 0.2s, box-shadow 0.2s',
        '&:hover': {
          transform: 'translateY(-2px)',
          boxShadow: '0 4px 20px rgba(0,0,0,0.1)'
        }
      }}
      onClick={() => navigate(`/vagas/${job.id}`)}
    >
      <CardContent sx={{ p: 3 }}>
        <Box sx={{ display: 'flex', alignItems: 'flex-start', mb: 2 }}>
          <Avatar 
            src={job.logo}
            sx={{ width: 50, height: 50, mr: 2 }}
          >
            <Business />
          </Avatar>
          <Box sx={{ flexGrow: 1 }}>
            <Typography variant="h6" fontWeight="bold" gutterBottom>
              {job.title}
            </Typography>
            <Typography color="text.secondary" variant="body2">
              {job.company}
            </Typography>
          </Box>
          <Typography variant="caption" color="text.secondary">
            {job.postedAt}
          </Typography>
        </Box>
        
        <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
          {job.description}
        </Typography>
        
        <Box sx={{ display: 'flex', alignItems: 'center', mb: 2, gap: 2 }}>
          <Box sx={{ display: 'flex', alignItems: 'center' }}>
            <LocationOn sx={{ fontSize: 16, mr: 0.5, color: 'text.secondary' }} />
            <Typography variant="body2" color="text.secondary">
              {job.location}
            </Typography>
          </Box>
          <Box sx={{ display: 'flex', alignItems: 'center' }}>
            <Work sx={{ fontSize: 16, mr: 0.5, color: 'text.secondary' }} />
            <Typography variant="body2" color="text.secondary">
              {job.experience}
            </Typography>
          </Box>
        </Box>
        
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Box sx={{ display: 'flex', gap: 1 }}>
            <Chip 
              label={job.jobType} 
              size="small" 
              variant="outlined" 
              color="primary"
            />
            <Chip 
              label={job.category} 
              size="small" 
              variant="outlined"
            />
          </Box>
          <Typography variant="h6" color="primary" fontWeight="bold">
            {job.salary}
          </Typography>
        </Box>
      </CardContent>
    </Card>
  );

  const JobSkeleton = () => (
    <Card>
      <CardContent sx={{ p: 3 }}>
        <Box sx={{ display: 'flex', alignItems: 'flex-start', mb: 2 }}>
          <Skeleton variant="circular" width={50} height={50} sx={{ mr: 2 }} />
          <Box sx={{ flexGrow: 1 }}>
            <Skeleton variant="text" width="60%" height={28} />
            <Skeleton variant="text" width="40%" height={20} />
          </Box>
        </Box>
        <Skeleton variant="text" width="100%" height={20} sx={{ mb: 2 }} />
        <Skeleton variant="text" width="80%" height={20} sx={{ mb: 2 }} />
        <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
          <Skeleton variant="rectangular" width={100} height={24} />
          <Skeleton variant="text" width={120} height={24} />
        </Box>
      </CardContent>
    </Card>
  );

  return (
    <>
      <Helmet>
        <title>Vagas de Emprego - Moçambique | {jobs.length} oportunidades disponíveis</title>
        <meta name="description" content="Explore milhares de vagas de emprego em Moçambique. Encontre sua próxima oportunidade profissional." />
      </Helmet>

      <Container maxWidth="lg" sx={{ py: 4 }}>
        <Typography variant="h4" component="h1" gutterBottom fontWeight="bold">
          Vagas de Emprego
        </Typography>
        <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
          {loading ? 'Carregando...' : `${jobs.length} vagas encontradas`}
        </Typography>

        <Grid container spacing={3}>
          {/* Filtros */}
          <Grid item xs={12} md={3}>
            <Paper sx={{ p: 3, position: 'sticky', top: 20 }}>
              <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                <FilterList sx={{ mr: 1 }} />
                <Typography variant="h6" fontWeight="bold">
                  Filtros
                </Typography>
              </Box>

              <TextField
                fullWidth
                label="Pesquisar"
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon />
                    </InputAdornment>
                  )
                }}
                sx={{ mb: 2 }}
              />

              <FormControl fullWidth sx={{ mb: 2 }}>
                <InputLabel>Localização</InputLabel>
                <Select
                  value={filters.location}
                  label="Localização"
                  onChange={(e) => handleFilterChange('location', e.target.value)}
                >
                  <MenuItem value="">Todas</MenuItem>
                  {locations.map(location => (
                    <MenuItem key={location} value={location}>
                      {location}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl fullWidth sx={{ mb: 2 }}>
                <InputLabel>Área</InputLabel>
                <Select
                  value={filters.category}
                  label="Área"
                  onChange={(e) => handleFilterChange('category', e.target.value)}
                >
                  <MenuItem value="">Todas</MenuItem>
                  {jobCategories.map(category => (
                    <MenuItem key={category} value={category}>
                      {category}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl fullWidth sx={{ mb: 2 }}>
                <InputLabel>Tipo</InputLabel>
                <Select
                  value={filters.jobType}
                  label="Tipo"
                  onChange={(e) => handleFilterChange('jobType', e.target.value)}
                >
                  <MenuItem value="">Todos</MenuItem>
                  {jobTypes.map(type => (
                    <MenuItem key={type} value={type}>
                      {type}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl fullWidth sx={{ mb: 3 }}>
                <InputLabel>Experiência</InputLabel>
                <Select
                  value={filters.experience}
                  label="Experiência"
                  onChange={(e) => handleFilterChange('experience', e.target.value)}
                >
                  <MenuItem value="">Todos</MenuItem>
                  {experienceLevels.map(level => (
                    <MenuItem key={level} value={level}>
                      {level}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <Button 
                fullWidth 
                variant="outlined" 
                onClick={clearFilters}
              >
                Limpar Filtros
              </Button>
            </Paper>
          </Grid>

          {/* Lista de vagas */}
          <Grid item xs={12} md={9}>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              {loading ? (
                Array.from({ length: 6 }).map((_, index) => (
                  <JobSkeleton key={index} />
                ))
              ) : jobs.length > 0 ? (
                jobs.map(job => (
                  <JobCard key={job.id} job={job} />
                ))
              ) : (
                <Paper sx={{ p: 4, textAlign: 'center' }}>
                  <Typography variant="h6" color="text.secondary">
                    Nenhuma vaga encontrada
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    Tente ajustar os filtros para encontrar mais resultados
                  </Typography>
                </Paper>
              )}
            </Box>

            {/* Paginação */}
            {totalPages > 1 && (
              <Box sx={{ display: 'flex', justifyContent: 'center', mt: 4 }}>
                <Pagination
                  count={totalPages}
                  page={currentPage}
                  onChange={(_, page) => setCurrentPage(page)}
                  color="primary"
                  size="large"
                />
              </Box>
            )}
          </Grid>
        </Grid>
      </Container>
    </>
  );
}

export default JobsPage;