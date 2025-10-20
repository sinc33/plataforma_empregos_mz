import React from 'react';
import { Container, Paper, Box, Typography, Button, Chip, Grid, Avatar } from '@mui/material';
import { Work, Business, LocationOn, AttachMoney } from '@mui/icons-material';
import { useParams, useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';

function JobDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();

  // TODO: substituir por chamada à API
  const job = {
    id,
    title: 'Desenvolvedor Full Stack',
    company: 'TechMoz Solutions',
    location: 'Maputo',
    salary: '45.000 - 65.000 MZN',
    jobType: 'Tempo Integral',
    category: 'Tecnologia',
    experience: 'Médio',
    description: 'Responsável por desenvolver funcionalidades frontend e backend, integração com APIs, testes e deploy.',
    requirements: [
      '3+ anos com React e Node',
      'Experiência com REST APIs',
      'Conhecimentos de SQL e NoSQL',
      'Git e CI/CD'
    ],
    benefits: ['Plano de saúde', 'Bónus anual', 'Horário flexível'],
  };

  return (
    <>
      <Helmet>
        <title>{job.title} | {job.company}</title>
      </Helmet>
      <Container maxWidth="md" sx={{ py: 4 }}>
        <Paper sx={{ p: 3 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
            <Avatar sx={{ width: 56, height: 56, mr: 2 }}>
              <Business />
            </Avatar>
            <Box>
              <Typography variant="h5" fontWeight="bold">{job.title}</Typography>
              <Typography color="text.secondary">{job.company}</Typography>
            </Box>
          </Box>

          <Grid container spacing={2} sx={{ mb: 2 }}>
            <Grid item>
              <Chip icon={<LocationOn />} label={job.location} />
            </Grid>
            <Grid item>
              <Chip icon={<Work />} label={job.jobType} color="primary" variant="outlined" />
            </Grid>
            <Grid item>
              <Chip icon={<AttachMoney />} label={job.salary} color="success" />
            </Grid>
          </Grid>

          <Typography variant="h6" sx={{ mt: 2, mb: 1 }}>Descrição</Typography>
          <Typography variant="body1" color="text.secondary" sx={{ mb: 2 }}>{job.description}</Typography>

          <Typography variant="h6" sx={{ mt: 2, mb: 1 }}>Requisitos</Typography>
          <ul>
            {job.requirements.map((req) => (
              <li key={req}>
                <Typography variant="body2" color="text.secondary">{req}</Typography>
              </li>
            ))}
          </ul>

          <Typography variant="h6" sx={{ mt: 2, mb: 1 }}>Benefícios</Typography>
          <ul>
            {job.benefits.map((b) => (
              <li key={b}>
                <Typography variant="body2" color="text.secondary">{b}</Typography>
              </li>
            ))}
          </ul>

          <Box sx={{ display: 'flex', gap: 2, mt: 3 }}>
            <Button variant="contained" size="large" onClick={() => navigate(`/candidate/applications`)}>
              Candidatar
            </Button>
            <Button variant="outlined" onClick={() => navigate('/vagas')}>Voltar às Vagas</Button>
          </Box>
        </Paper>
      </Container>
    </>
  );
}

export default JobDetailPage;
